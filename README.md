# Choven - Водные приключения в Беларуси

![Choven](https://via.placeholder.com/800x200?text=Choven.by)

## Описание проекта

**Choven.by** - веб-платформа для организации водных сплавов и путешествий на байдарках по рекам Беларуси. Проект разработан на Symfony с многоязычным интерфейсом (русский, белорусский, английский).

---

## Требования

- **PHP** 8.1+
- **Composer**
- **Symfony CLI**
- **Docker** и **Docker Compose**
- **PostgreSQL**

---

## Установка и настройка

### 1. Клонирование репозитория

```bash
git clone https://github.com/ChewyPegasus/choven.by.git
```

### 2. Установка зависимостей

```bash
composer install
```

### 3. Настройка окружения

Создайте файл `.env.local` на основе примера `.env`.

Отредактируйте `.env.local`, указав параметры подключения к базе данных и другие настройки.

### 4. Запуск Docker-контейнеров

```bash
docker-compose up -d
```

### 5. Создание и настройка базы данных

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 6. Запуск веб-сервера для разработки

```bash
symfony server:start
```

Приложение будет доступно по адресу: [http://localhost:8000](http://localhost:8000)

---

## Команды для разработки

### Создание миграции после изменения сущностей

```bash
php bin/console make:migration
```

### Очистка кэша

```bash
php bin/console cache:clear
```

### Работа с переводами

Файлы переводов находятся в директории `translations/`:

- `messages.ru.yaml` - русский язык
- `messages.be.yaml` - белорусский язык
- `messages.en.yaml` - английский язык

---

## Структура проекта

- `src/Controller/` - контроллеры приложения
- `src/Entity/` - сущности Doctrine
- `src/Enum/` - перечисления (типы сплавов, реки и т.д.)
- `src/Form/` - формы
- `src/Service/` - сервисы
- `templates/` - шаблоны Twig
- `translations/` - файлы переводов
- `assets/` - CSS, JavaScript, изображения

---

## Лицензия

© 2024 Choven.by. Все права защищены.