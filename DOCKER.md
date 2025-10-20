# Docker instructions for this PHP + Apache + SQLite project

This repo includes a Dockerfile and docker-compose.yml to run a PHP 8.2 app on Apache with SQLite and Composer available in the container.

Quick start

1. Build and start the service:

   docker compose up --build -d

2. Open your app at: http://localhost:8080

Composer notes with bind mounts

- The Dockerfile will run `composer install` at build time if a `composer.json` is present in the repo. When using a bind mount (the compose file mounts the project into the container), vendor files created at build-time may be shadowed by the host filesystem.
- If you change dependencies after starting the container, run composer inside the container:

  docker compose exec web composer install --no-interaction

SQLite file location

- Place your SQLite database file (e.g., `database.sqlite`) inside the project (for example `./database/database.sqlite`) and ensure it's writable by the web server user `www-data`.

Permissions

- If you run into permission issues with uploaded files or the SQLite file, set ownership from the host:

  sudo chown -R $(id -u):$(id -g) ./path/to/storage

Or run inside container to change ownership:

  docker compose exec web chown www-data:www-data /var/www/html/path/to/storage

Development tips

- For production deployments you may want to not mount the entire project directory and instead copy only the necessary artifacts into the image.
- Consider using a data-only volume for the SQLite DB if you want to persist it outside the project tree.
