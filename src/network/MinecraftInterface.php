<?php

class MinecraftInterface{
	public static $LIMIT_ACKS = true;

	public $bandwidth;
	private $socket;
	private $packets;
	public $start;
	public $maxACKsAllowed = [];
	public $maxNACKsAllowed = [];
	public $maxRecordedSeq = [];

	function __construct($server, $port = 25565, $serverip = "0.0.0.0"){
		$this->socket = new UDPSocket($server, $port, true, $serverip);
		if($this->socket->connected === false){
			console("[SEVERE] Couldn't bind to $serverip:" . $port, true, true, 0);
			exit(1);
		}
		$this->bandwidth = [0, 0, microtime(true)];
		$this->start = microtime(true);
		$this->packets = [];
	}

	public function close(){
		return $this->socket->close(false);
	}

	public function readPacket(){
		if($this->socket->connected === false){
			return false;
		}
		$buf = "";
		$source = false;
		$port = 1;
		$len = $this->socket->read($buf, $source, $port);
		if($len === false or $len === 0){
			return false;
		}
		$this->bandwidth[0] += $len;
		ServerAPI::request()->api->dhandle("mcinterface.read", ["buffer" => $buf, "source" => $source, "port" => $port]);
		return $this->parsePacket($buf, $source, $port);
	}

	public function clear($ip, $port){
		$id = "$ip:$port";
		unset($this->maxACKsAllowed[$id]);
		unset($this->maxNACKsAllowed[$id]);
		unset($this->maxRecordedSeq[$id]);
	}

	public function init($ip, $port){
		$id = "$ip:$port";
		$this->maxACKsAllowed[$id] = 0;
		$this->maxNACKsAllowed[$id] = 0;
		unset($this->maxRecordedSeq[$id]);
	}

	private function parsePacket($buffer, $source, $port){
		$pid = ord($buffer[0]);

		if(RakNetInfo::isValid($pid)){
			$id = "$source:$port";
			$ackCnt = 0;
			$nackCnt = 0;
			$acks = $this->maxACKsAllowed[$id] ?? 0;
			$nacks = $this->maxNACKsAllowed[$id] ?? 0;
			if(!static::$LIMIT_ACKS){
				$acks = -1;
				$nacks = -1;
			}

			$packet = RakNetParser::parse($buffer, $source, $port, $acks, $ackCnt, $nacks, $nackCnt);
			if($packet !== false){
				if(static::$LIMIT_ACKS && $ackCnt != 0){
					$pid = $packet->pid();
					if($pid === RakNetInfo::ACK && isset($this->maxACKsAllowed[$id])){
						$this->maxACKsAllowed[$id] = $acks - $ackCnt;
					}elseif($pid === RakNetInfo::NACK && isset($this->maxNACKsAllowed[$id])){
						$this->maxNACKsAllowed[$id] = $nacks - $ackCnt;
					}
				}
				$packet->ip = $source;
				$packet->port = $port;
				if(EventHandler::callEvent(new PacketReceiveEvent($packet)) === BaseEvent::DENY){
					return false;
				}
				return $packet;
			}elseif(static::$LIMIT_ACKS && $ackCnt > 0){
				$CID = PocketMinecraftServer::clientID($source, $port);
				$server = ServerAPI::request();
				$c = $server->clients[$CID] ?? false;
				if($c !== false){
					$username = $c->iusername == "" ? $id : $c->iusername;
					if($nackCnt > 0){
						ConsoleAPI::warn("Player '$username' sent too many NACK packets (allowed: {$nacks}, attempted {$ackCnt} or more)!");
					}else{
						ConsoleAPI::warn("Player '$username' sent too many ACK packets (allowed: {$acks}, attempted {$ackCnt} or more)!");
					}
					$c->close("too many ack packets");
				}
			}
			return false;
		}elseif($pid === 0xfe and $buffer[1] === "\xfd" and ServerAPI::request()->api->query instanceof QueryHandler){
			$packet = new QueryPacket;
			$packet->ip = $source;
			$packet->port = $port;
			$packet->buffer =& $buffer;
			if(EventHandler::callEvent(new PacketReceiveEvent($packet)) === BaseEvent::DENY){
				return false;
			}
			ServerAPI::request()->api->query->handle($packet);
		}else{
			$packet = new Packet();
			$packet->ip = $source;
			$packet->port = $port;
			$packet->buffer =& $buffer;
			EventHandler::callEvent(new PacketReceiveEvent($packet));
			return false;
		}
	}

	public function writePacket(Packet $packet){
		if(EventHandler::callEvent(new PacketSendEvent($packet)) === BaseEvent::DENY){
			return 0;
		}elseif($packet instanceof RakNetPacket){
			RakNetCodec::encode($packet);
			if($packet->pid() >= RakNetInfo::DATA_PACKET_0 && $packet->pid() <= RakNetInfo::DATA_PACKET_F && $packet->incrementAllowedAck){
				$id = "{$packet->ip}:{$packet->port}";
				$seq = $this->maxRecordedSeq[$id] ?? false;
				$nackIncr = 0;
				if($seq === false){
					$this->maxRecordedSeq[$id] = $packet->seqNumber;
					$nackIncr = 1;
				}elseif($packet->seqNumber > $seq){
					$nackIncr = $packet->seqNumber - $seq;
					$this->maxRecordedSeq[$id] = $packet->seqNumber;
				}
				if($nackIncr > 1000){
					$nackIncr = 1000;
				}
				if(isset($this->maxNACKsAllowed[$id])){
					$this->maxNACKsAllowed[$id] += $nackIncr;
				}else{
					$this->maxNACKsAllowed[$id] = $nackIncr;
				}
				if(isset($this->maxACKsAllowed[$id])){
					if($this->maxACKsAllowed[$id] < 65536){
						++$this->maxACKsAllowed[$id];
					}
				}else{
					$this->maxACKsAllowed[$id] = 1;
				}
			}
		}
		$write = $this->socket->write($packet->buffer, $packet->ip, $packet->port);
		$this->bandwidth[1] += $write;
		return $write;
	}
}
