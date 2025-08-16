# Docker Environment

This directory contains all the configuration files for the project's Docker-based development environment. The goal is to provide a consistent and isolated environment that mirrors a typical production server.

The primary interface for managing this environment is the `../bin/docker` script. This document explains the underlying components.

---

## Services

The environment is defined in `docker-compose.yml` and consists of the following services:

| Service Name | Image Base | Description                                                                                                                                                                      |
|--------------|------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `app`        | `php:cli`  | The main application container, built from the local `Dockerfile`. It runs PHP and contains all development tools like Composer and Xdebug. Your project root is mounted into `/app`. |
| `db`         | `mysql:8.0`  | A MySQL database service for development and integration testing.                                                                                                                |

---

## Configuration (`docker/.env`)

The environment is customized by creating and editing the `docker/.env` file (which `../bin/init` creates for you).

| Variable                 | Default           | Description                                                                                                                                             |
| ------------------------ | ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `COMPOSE_PROJECT_NAME`     | `wp-tables-schema` | A unique name for the project's Docker containers and networks. Changing this prevents conflicts with other Docker projects.                            |
| `PHP_VERSION`              | `8.4`             | The version of PHP to build and run in the `app` container.                                                                                               |
| `UID`                      | `1000`            | The user ID to run as inside the `app` container. **This should match your host user's ID** to avoid file permission issues.                              |
| `GID`                      | `1000`            | The group ID to run as inside the `app` container. **This should match your host user's group ID**.                                                     |
| `MYSQL_PORT`               | `3306`            | The external port on your host machine that maps to the MySQL container. Change this if you have another service using port 3306.                        |
| `WORDPRESS_TESTS_DATABASE` | `wordpress_tests` | The database created specifically for running PHPUnit tests.                                                                                              |
| `MYSQL_USER`               | `wordpress`       | The username for the MySQL tests database.                                                                                                              |
| `MYSQL_PASSWORD`           | `wordpress`       | The password for the MySQL tests user.                                                                                                                  |
| `MYSQL_ROOT_PASSWORD`      | `root`            | The root password for the MySQL server.                                                                     |

*Tip: You can find your local user and group ID by running `id -u` and `id -g` in your host terminal.*

---

## Application Container (`Dockerfile`)

The `app` service is built from the `Dockerfile` in this directory. It is responsible for setting up a complete PHP development environment.

-   **Base Image:** It starts from an official `php:cli-alpine` image for a lightweight footprint.
-   **Key Software:**
    -   **PHP:** The version is determined by the `PHP_VERSION` variable in `.env`.
    -   **PHP Extensions:** Installs `bcmath`, `exif`, `intl`, `mbstring`, and `xdebug`.
    -   **Composer:** The latest version of Composer is installed globally.
-   **User:** A non-root user named `developer` is created using the `UID` and `GID` from the `.env` file. This is crucial for correct file permissions.

### Customizing PHP

You can customize PHP settings by editing the `php.ini` file in this directory. It is automatically mounted into the `app` container. If you make changes to this file, you may need to restart the container for them to take effect:

```bash
../bin/docker restart app
```

### Adding PHP Extensions

To add a new PHP extension:
1.  Add the extension name to the `docker-php-ext-install` list in the `Dockerfile`.
2.  Rebuild the `app` container for the changes to be applied:

```bash
../bin/docker build app
```

---

## Data Persistence

This setup uses named Docker volumes to ensure that important data is not lost when you stop and start your containers.

-   **Database Data (`wp_pkg_mysql_data`):** All MySQL database files are stored in this volume. This means your database state will persist even after running `../bin/docker down`.
-   **WordPress Test Suite (`wp_pkg_tests_data`):** The WordPress test suite files, which are downloaded by the `install-wp-tests` script, are stored in this volume. This saves you from having to re-download them every time you rebuild the `app` container.
-   **Application Code:** Your project code is not stored in a volume but is mounted directly from your host machine into the `/app` directory of the container. Changes you make locally are reflected instantly inside the container.
