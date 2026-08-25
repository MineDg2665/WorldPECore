# 📚 WorldPECore — Plugin API Documentation

> Документация по созданию плагинов для ядра **WorldPECore** (Plugin API `12.2`, MCPE v0.8.1 alpha, PHP ≥ 8.0).
> Все сигнатуры и события выверены по исходникам ядра.

**Язык / Language:** [Русский](README.md) | [English](en/README.md)

## Разделы

| # | Файл | Содержание |
|---|------|------------|
| 1 | [Introduction & Architecture](01-introduction.md) | Что такое ядро, архитектура (Mermaid-диаграммы), tick loop, сетевой стек, поток пакетов клиент↔сервер, жизненный цикл сессии игрока, карта `src/`, константы |
| 2 | [Plugin Lifecycle & Quickstart](02-plugin-lifecycle.md) | Интерфейс `Plugin`, метаданные заголовка, форматы `.php/.pmf/.phar` (+сборка PHAR), жизненный цикл с диаграммой, boilerplate, паттерны: GUI-сундук, арена, подкоманды, свои события, конфиги |
| 3 | [Core API Reference](03-core-api-reference.md) | Полный справочник: `ServerAPI`, `PocketMinecraftServer`, 12 сервисов (`Console/Chat/Player/Level/Block/Entity/Tile/Ban/Time/Achievement/Query/PluginAPI`), `Level/Player/Entity/Block/Item`, пакеты ProtocolInfo + cookbook рецептов |
| 4 | [Events, Hooks & Extensions](04-events-hooks.md) | Каталог ~60 legacy-событий с payload и семантикой отмены; OOP-система (`BaseEvent`, `EventPriority`); пакетные события; матрица «что как отменять»; диагностика хендлеров |
| 5 | [Best Practices & Security](05-best-practices.md) | Модель потоков, асинхронность (`asyncOperation`, `Async`), память, безопасность (SQLi, traversal, issuer-матрица), обработка ошибок, антипаттерны, сквозной кейс ArenaPvP |

## Быстрый старт

1. Прочитайте [Часть 2 §4](02-plugin-lifecycle.md#4-быстрый-старт-минимальный-boilerplate) — минимальный boilerplate плагина.
2. Положите файл в `plugins/ВашПлагин/ВашПлагин.php`, перезапустите сервер.
3. Проверьте установку командой `/plugins`.

## Для кого

- **Разработчикам плагинов** — части 2–5.
- **Модифицирующим ядро** — часть 1 (архитектура, tick loop, проводные форматы) и часть 3.
