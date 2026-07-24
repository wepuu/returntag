#!/usr/bin/env bash

set -euo pipefail

: "${WP_VERSION:?WP_VERSION is required}"
: "${WP_DEVELOP_DIR:?WP_DEVELOP_DIR is required}"
: "${DB_NAME:?DB_NAME is required}"
: "${DB_USER:?DB_USER is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"
: "${DB_HOST:?DB_HOST is required}"

if [[ -e "${WP_DEVELOP_DIR}" ]]; then
	echo "WordPress test destination already exists." >&2
	exit 1
fi

git clone \
	--branch "${WP_VERSION}" \
	--depth 1 \
	https://github.com/WordPress/wordpress-develop.git \
	"${WP_DEVELOP_DIR}"

config_file="${WP_DEVELOP_DIR}/wp-tests-config.php"
cp "${WP_DEVELOP_DIR}/wp-tests-config-sample.php" "${config_file}"

php -r '
$path = $argv[1];
$contents = file_get_contents($path);

if (! is_string($contents)) {
    fwrite(STDERR, "WordPress test configuration could not be read.\n");
    exit(1);
}

$configured = str_replace(
    array(
        "youremptytestdbnamehere",
        "yourusernamehere",
        "yourpasswordhere",
        "localhost",
    ),
    array(
        getenv("DB_NAME"),
        getenv("DB_USER"),
        getenv("DB_PASSWORD"),
        getenv("DB_HOST"),
    ),
    $contents
);

if ($configured === $contents || false === file_put_contents($path, $configured)) {
    fwrite(STDERR, "WordPress test configuration could not be created.\n");
    exit(1);
}
' "${config_file}"
