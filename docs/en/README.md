# 📚 WorldPECore — Plugin API Documentation

> Documentation for building plugins on top of the **WorldPECore** kernel (Plugin API `12.2`, MCPE v0.8.1 alpha, PHP ≥ 8.0).
> Every signature and event listed here was verified against the kernel sources.

**Language / Язык:** [English](README.md) | [Русский](../README.md)

## Sections

| # | File | Contents |
|---|------|----------|
| 1 | [Introduction & Architecture](01-introduction.md) | What the kernel is, architecture (Mermaid diagrams), tick loop, network stack, client↔server packet flow, player session lifecycle, `src/` map, constants |
| 2 | [Plugin Lifecycle & Quickstart](02-plugin-lifecycle.md) | The `Plugin` interface, metadata header, `.php/.pmf/.phar` formats (+PHAR build guide), lifecycle diagram, boilerplate, patterns: chest GUI, arena, subcommands, custom events, configs |
| 3 | [Core API Reference](03-core-api-reference.md) | Full reference: `ServerAPI`, `PocketMinecraftServer`, 12 services (`Console/Chat/Player/Level/Block/Entity/Tile/Ban/Time/Achievement/Query/PluginAPI`), `Level/Player/Entity/Block/Item`, ProtocolInfo packets + cookbook recipes |
| 4 | [Events, Hooks & Extensions](04-events-hooks.md) | Catalog of ~60 legacy events with payloads and cancellation semantics; OOP system (`BaseEvent`, `EventPriority`); packet events; “what cancels what” matrix; handler debugging |
| 5 | [Best Practices & Security](05-best-practices.md) | Threading model, async (`asyncOperation`, `Async`), memory, security (SQLi, traversal, issuer matrix), error handling, anti-patterns, end-to-end ArenaPvP case study |

## Quick start

1. Read [Part 2 §4](02-plugin-lifecycle.md#4-quickstart-minimal-boilerplate) — the minimal plugin boilerplate.
2. Drop the file into `plugins/YourPlugin/YourPlugin.php` and restart the server.
3. Verify installation with `/plugins`.

## Audience

- **Plugin developers** — parts 2–5.
- **Kernel hackers** — part 1 (architecture, tick loop, wire formats) and part 3.
