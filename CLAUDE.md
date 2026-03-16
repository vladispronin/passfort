# PassFort — Менеджер паролей

## Описание проекта
PassFort — веб-приложение для безопасного хранения и управления паролями. Концепция: «крепость для паролей» (password + fortress). Zero-knowledge архитектура — сервер никогда не видит расшифрованные данные пользователя.

## Стек технологий
- **Frontend:** Vue.js 3 (SPA, Composition API, TypeScript)
- **Backend:** PHP 8.3 + Symfony 7 (REST API)
- **База данных:** MySQL 8
- **Очереди:** Apache Kafka
- **Кэш/сессии:** Redis
- **Веб-сервер:** Nginx

## Архитектура
- Тип: SPA + REST API
- Аутентификация: JWT (access + refresh токены)
- Шифрование на стороне клиента: AES-256-GCM
- Мастер-пароль никогда не передаётся на сервер — только производный ключ для верификации
- Ключ шифрования деривируется из мастер-пароля через PBKDF2 или Argon2 на клиенте

## Безопасность — критические требования
- **AES-256-GCM** для шифрования записей (на стороне клиента)
- **Argon2id** для хэширования паролей на сервере
- **HTTPS-only** — никаких незашифрованных соединений
- **Zero-knowledge:** сервер хранит только зашифрованные блобы, ключ у пользователя
- CSRF-защита на всех мутирующих эндпоинтах
- Rate limiting на эндпоинтах аутентификации
- Все входные данные валидируются и санируются
- Заголовки безопасности: CSP, HSTS, X-Frame-Options, X-Content-Type-Options

## Структура директорий
```
/opt/project/passfort/
├── frontend/          # Vue.js SPA
│   ├── src/
│   │   ├── components/
│   │   ├── views/
│   │   ├── stores/    # Pinia
│   │   ├── composables/
│   │   ├── crypto/    # Криптографические утилиты (клиентское шифрование)
│   │   └── api/       # Axios-клиент
│   ├── public/
│   ├── package.json
│   └── vite.config.ts
├── backend/           # Symfony REST API
│   ├── src/
│   │   ├── Controller/
│   │   ├── Entity/
│   │   ├── Repository/
│   │   ├── Service/
│   │   ├── Security/
│   │   └── DTO/
│   ├── config/
│   ├── migrations/
│   ├── tests/
│   └── composer.json
├── docker/            # Docker-конфигурация
│   ├── nginx/
│   ├── php/
│   ├── mysql/
│   └── kafka/
├── docker-compose.yml
├── docker-compose.prod.yml
└── CLAUDE.md
```

## Конвенции кода

### PHP / Symfony
- Стандарт: **PSR-12**
- Строгая типизация: `declare(strict_types=1)` во всех файлах
- Именование: классы — PascalCase, методы — camelCase, константы — UPPER_SNAKE_CASE
- Репозитории только для запросов к БД, бизнес-логика — в сервисах
- DTO для валидации входных данных (Symfony Validator)
- Все ответы API возвращают JSON через `JsonResponse`

### Vue.js / TypeScript
- Стандарт: **ESLint** (конфиг Vue + TypeScript)
- Composition API с `<script setup>`
- Состояние: **Pinia** (не Vuex)
- Стили: **Tailwind CSS** или scoped CSS
- Именование компонентов: PascalCase
- Composables с префиксом `use` (например `useCrypto`, `useAuth`)

## Тестирование

### Backend (PHPUnit)
- Unit-тесты для всех сервисов и крипто-утилит
- Функциональные тесты для всех API-эндпоинтов
- Покрытие критических путей безопасности — 100%
- Запуск: `php bin/phpunit`

### Frontend (Vitest)
- Unit-тесты для composables и крипто-модулей
- Компонентные тесты через Vue Test Utils
- Особое внимание: тесты шифрования/дешифрования
- Запуск: `npm run test`

## Переменные окружения и секреты
- **НИКОГДА не коммитить** `.env`, `.env.local`, `.env.prod`
- Использовать `.env.example` с примерами без реальных значений
- Секреты в продакшне — через Docker secrets или переменные окружения сервера
- JWT_SECRET, DATABASE_URL, REDIS_URL, KAFKA_BROKERS — всегда через env, никогда в коде

## API-конвенции
- Базовый путь: `/api/v1/`
- Аутентификация: Bearer-токен в заголовке `Authorization`
- Формат ответа:
  ```json
  { "data": {}, "meta": {} }        // успех
  { "error": "message", "code": 400 } // ошибка
  ```
- HTTP-коды: 200 OK, 201 Created, 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found, 422 Unprocessable Entity, 500 Internal Server Error

## Основные сущности
- **User** — аккаунт пользователя (email, хэш мастер-пароля, соль)
- **Vault** — хранилище (у каждого пользователя своё)
- **VaultItem** — запись в хранилище (зашифрованный блоб: логин, пароль, URL, заметки)
- **Category** — категория для группировки записей

## Важные замечания для разработки
- Крипто-модуль на фронтенде — наиболее критичная часть. Тесты обязательны перед любыми изменениями.
- При изменении схемы БД — всегда создавать миграцию Doctrine, не менять схему напрямую. MySQL 8 в качестве движка таблиц использует InnoDB.
- Kafka используется для асинхронных задач (например, отправка email-уведомлений, аудит-лог событий безопасности). Symfony Messenger — транспорт для Kafka.
- Логировать попытки входа (успешные и неуспешные) для аудита безопасности.
- Никаких `console.log` с чувствительными данными в продакшне.
