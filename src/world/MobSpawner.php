<?php

class MobSpawner{
	public static $spawnAnimals = false, $spawnMobs = false, $maxMobsNearPlayerAtOnce = 10;
	private $server;
	public $level;
	public static $MOB_LIMIT = 50;

	private static $playerIndex = 0;
	private $tickCounter = 0;

	public function __construct(Level $level){
		$this->server = ServerAPI::request();
		$this->level = $level;
	}

	public function countEntities(){
		return $this->level->totalMobsAmount;
	}

	public function checkDespawn(Living $living){
		$nearestSq = $this->getNearestPlayerDistSq($living->x, $living->y, $living->z);
		if($nearestSq <= 512){
			return false;
		}
		return $nearestSq > 4096 || mt_rand((int)$nearestSq, 4096) == (int)$nearestSq;
	}

	public function handle(){
		if(++$this->tickCounter <= 1){
			return false;
		}
		$this->tickCounter = 0;

		$hostiles = self::$spawnMobs && $this->server->difficulty > 0;
		$animals = self::$spawnAnimals && ($this->level->getTime() % 400) <= 1;

		if(!$hostiles && !$animals){
			return false;
		}

		if($this->countEntities() > self::$MOB_LIMIT){
			return false;
		}

		if(count($this->level->players) <= 0){
			return false;
		}

		return $this->spawnMobs($hostiles, $animals);
	}

	private function spawnMobs($hostiles, $animals){
		$chunksToPoll = [];

		$usedChunksSet = $this->level->getUsedChunks();

		if($animals){
			foreach($usedChunksSet as $index => $users){
				$parts = explode(".", $index);
				$chunksToPoll[] = [(int)$parts[0], (int)$parts[1]];
			}
			$hostiles = false;
		}else{
			$players = $this->level->players;
			if(count($players) <= 0) return false;

			self::$playerIndex = (self::$playerIndex + 1) % count($players);
			$playerKeys = array_keys($players);
			$player = $players[$playerKeys[self::$playerIndex]];

			$chunkX = (int)floor($player->entity->x / 16);
			$chunkZ = (int)floor($player->entity->z / 16);

			for($dx = -8; $dx <= 8; ++$dx){
				for($dz = -8; $dz <= 8; ++$dz){
					$cx = $chunkX + $dx;
					$cz = $chunkZ + $dz;
					if($cx >= 0 && $cx <= 15 && $cz >= 0 && $cz <= 15 && isset($usedChunksSet["$cx.$cz"])){
						$chunksToPoll[] = [$cx, $cz];
					}
				}
			}
		}

		if(empty($chunksToPoll)){
			return false;
		}

		$spawnPos = $this->level->getSpawn();
		$totalSpawned = 0;

		$categories = [];
		if($hostiles){
			$categories[] = ['id' => 1, 'maxCount' => 20];
		}
		if($animals){
			$categories[] = ['id' => 2, 'maxCount' => 15];
		}

		foreach($categories as $category){
			$catCount = $this->countByCategory($category['id']);
			if($catCount > $category['maxCount']){
				continue;
			}

			$isMonster = $category['id'] === 1;
			$grassOnly = $category['id'] === 2;

			foreach($chunksToPoll as $chunk){
				$baseX = $chunk[0] * 16;
				$baseZ = $chunk[1] * 16;

				$x = $baseX + mt_rand(0, 15);
				$y = mt_rand(0, 127);
				$z = $baseZ + mt_rand(0, 15);

				if(StaticBlock::getIsSolid($this->level->level->getBlockID($x, $y, $z))){
					continue;
				}

				for($outer = 0; $outer < 3; ++$outer){
					$nx = $x;
					$nz = $z;
					$rarity = -128;
					$v41 = 0;
					$v40 = 999;

					for($inner = 0; $inner < 4; ++$inner){
						$nx += mt_rand(-5, 5);
						$nz += mt_rand(-5, 5);

						$nx = max(0, min(255, $nx));
						$nz = max(0, min(255, $nz));

						if($isMonster){
							if(!$this->isSpawnPositionOk($nx, $y, $nz)){
								continue;
							}
						}else{
							$topY = $this->getTopSolidBlock($nx, $nz);
							if($topY <= 0){
								continue;
							}
							if($this->level->level->getBlockID($nx, $topY - 1, $nz) !== GRASS){
								continue;
							}
							$y = $topY;
							if(!$this->isSpawnPositionOk($nx, $y, $nz)){
								continue;
							}
						}

						if($this->getNearestPlayerDistSq($nx, $y, $nz) < 576.0){
							continue;
						}

						$dX = $nx - $spawnPos->x;
						$dY = $y - $spawnPos->y;
						$dZ = $nz - $spawnPos->z;
						if($dX*$dX + $dY*$dY + $dZ*$dZ < 576.0){
							continue;
						}

						if($rarity < 0){
							if($isMonster){
								$mobType = mt_rand(32, 35);
							}else{
								$mobType = mt_rand(10, 13);
							}

							$maxPerType = $isMonster ? 8 : 5;
							if($this->countByType($mobType) >= $maxPerType){
								continue;
							}

							$rarity = 1;
							$v40 = mt_rand(1, 3);
							$v41 = 0;
						}

						if($isMonster){
							$sl = $this->level->getSkyLight($nx, $y, $nz);
							if($sl > mt_rand(0, 31)){
								continue;
							}
							$bl = $this->level->level->getBlockLight($nx, $y, $nz);
							$rb = $bl > $sl ? $bl : $sl;
							if($rb > mt_rand(0, 7)){
								continue;
							}
						}else{
							$rb = $this->level->getRawBrightness($nx, $y, $nz);
							if($rb < 9){
								continue;
							}
						}
						$spawnY = $y + 0.5;

						$data = [
							"x" => $nx + 0.5,
							"y" => $spawnY,
							"z" => $nz + 0.5
						];
						if(!$isMonster && lcg_value() < 0.5){
							$data["IsBaby"] = true;
						}

						$e = $this->server->api->entity->add($this->level, ENTITY_MOB, $mobType, $data);
						if($e instanceof Entity){
							$this->server->api->entity->spawnToAll($e);
							ConsoleAPI::debug("$mobType spawned at $nx, $spawnY, $nz");
							++$v41;
							++$totalSpawned;
						}

						if($v41 > $v40){
							break;
						}
					}
				}
			}
		}

		return $totalSpawned > 0;
	}

	private function isSpawnPositionOk($x, $y, $z){
		if(!StaticBlock::getIsSolid($this->level->level->getBlockID($x, $y - 1, $z))){
			return false;
		}
		$block = $this->level->level->getBlockID($x, $y, $z);
		if(StaticBlock::getIsSolid($block) || StaticBlock::getIsLiquid($block)){
			return false;
		}
		if(StaticBlock::getIsSolid($this->level->level->getBlockID($x, $y + 1, $z))){
			return false;
		}
		return true;
	}

	private function getTopSolidBlock($x, $z){
		for($y = 127; $y >= 0; --$y){
			if(StaticBlock::getIsSolid($this->level->level->getBlockID($x, $y, $z))){
				return $y + 1;
			}
		}
		return -1;
	}

	private function getNearestPlayerDistSq($x, $y, $z){
		$minDist = INF;
		foreach($this->level->players as $player){
			if(!$player->spawned) continue;
			$dx = $player->entity->x - $x;
			$dy = $player->entity->y - $y;
			$dz = $player->entity->z - $z;
			$dist = $dx*$dx + $dy*$dy + $dz*$dz;
			if($dist < $minDist){
				$minDist = $dist;
			}
		}
		return $minDist;
	}

	private function countByCategory($categoryId){
		$count = 0;
		foreach($this->level->entityList as $e){
			if(!($e instanceof Entity) || $e->isPlayer()) continue;
			if($e->class === ENTITY_MOB){
				if($categoryId === 1 && $e->type >= 32 && $e->type <= 36){
					++$count;
				}elseif($categoryId === 2 && $e->type >= 10 && $e->type <= 13){
					++$count;
				}
			}
		}
		return $count;
	}

	private function countByType($type){
		$count = 0;
		foreach($this->level->entityList as $e){
			if(!($e instanceof Entity) || $e->isPlayer()) continue;
			if($e->class === ENTITY_MOB && $e->type === $type){
				++$count;
			}
		}
		return $count;
	}

	protected function getSafeY($x, $z, $grassOnly = false, $highMob = false, $isMonster = false, $preferredY = -1){
		$yStart = $preferredY >= 0 ? $preferredY : mt_rand(0, 127);

		for($dy = 0; $dy < 5; ++$dy){
			foreach([$yStart + $dy, $yStart - $dy] as $y){
				if($y < 0 || $y > 127) continue;
				$b = $this->level->level->getBlockID($x, $y, $z);
				$b1 = $this->level->level->getBlockID($x, $y - 1, $z);
				$b2 = $this->level->level->getBlockID($x, $y + 1, $z);

				if(
					!StaticBlock::getIsSolid($b) && !StaticBlock::getIsLiquid($b) &&
					StaticBlock::getIsSolid($b1) && (!$grassOnly || $b1 === GRASS) &&
					(!$highMob || (!StaticBlock::getIsSolid($b2) && !StaticBlock::getIsLiquid($b2)))
				){
					if($isMonster){
						$sl = $this->level->getSkyLight($x, $y, $z);
						$rb = $this->level->getRawBrightness($x, $y, $z);
						if($sl > mt_rand(0, 31) && $rb > mt_rand(0, 7)) continue;
					}
					return $y;
				}
			}
		}

		for($attempt = 0; $attempt < 10; ++$attempt){
			$y = mt_rand(0, 127);
			$b = $this->level->level->getBlockID($x, $y, $z);
			$b1 = $this->level->level->getBlockID($x, $y - 1, $z);
			$b2 = $this->level->level->getBlockID($x, $y + 1, $z);

			if(
				!StaticBlock::getIsSolid($b) && !StaticBlock::getIsLiquid($b) &&
				StaticBlock::getIsSolid($b1) && (!$grassOnly || $b1 === GRASS) &&
				(!$highMob || (!StaticBlock::getIsSolid($b2) && !StaticBlock::getIsLiquid($b2)))
			){
				if($isMonster){
					$sl = $this->level->getSkyLight($x, $y, $z);
					$rb = $this->level->getRawBrightness($x, $y, $z);
					if($sl > mt_rand(0, 31) && $rb > mt_rand(0, 7)) continue;
				}
				return $y;
			}
		}
		return -1;
	}
}
