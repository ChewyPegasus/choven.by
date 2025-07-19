# Choven - Water Adventures in Belarus

![Choven](https://via.placeholder.com/800x200?text=Choven.by)

## Project Description

**Choven.by** - a web platform for organizing kayaking trips and water adventures on Belarusian rivers. The project is built with Symfony and features a multilingual interface (Russian, Belarusian, English).

---

## Requirements

- **PHP** 8.1+
- **Composer**
- **Symfony CLI**
- **Docker** and **Docker Compose**
- **PostgreSQL**
- **Make**

---

## Installation and Setup

### 1. Clone the repository

```bash
git clone https://github.com/ChewyPegasus/choven.by.git
```

### 2. Install dependencies and initialize the project

Run the following command:

```bash
make install
```

This command performs the following steps:

- Composer dependencies installation
- Environment setup
- Docker containers launch
- Database creation and migrations

### 3. Start the development server

```bash
make run
```

The application will be available at: [http://localhost:8000](http://localhost:8000)

---

## Development Commands

All common operations are available through `make` commands:

### Database operations

- **Create a new migration after entity changes**:
    ```bash
    make migration
    ```

- **Apply migrations**:
    ```bash
    make migrate
    ```

- **Reset database (drops and recreates)**:
    ```bash
    make db-reset
    ```

### Cache operations

- **Clear cache**:
    ```bash
    make cache-clear
    ```

### Translation operations

- **Extract all translation strings**:
    ```bash
    make translations-extract
    ```

- **Debug translations for a specific locale**:
    ```bash
    make translations-debug locale=be
    ```

Translation files are located in the `translations/` directory:

- `messages.ru.yaml` - Russian
- `messages.be.yaml` - Belarusian
- `messages.en.yaml` - English

### Quality checks

- **Run code style checks**:
    ```bash
    make lint
    ```

- **Run tests**:
    ```bash
    make test
    ```

---

## Project Structure

- `src/Controller/` - application controllers
- `src/Entity/` - Doctrine entities
- `src/Enum/` - enumerations (rafting types, rivers, etc.)
- `src/Form/` - forms
- `src/Service/` - services
- `templates/` - Twig templates
- `translations/` - translation files
- `assets/` - CSS, JavaScript, images

---

## License

© 2025 Choven.by. All rights reserved.