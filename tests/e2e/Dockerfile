ARG WP_IMAGE=wordpress:latest
FROM $WP_IMAGE

# Install NPM
RUN apt-get update && apt-get install -y gnupg2 nodejs npm

# Install WP Cli
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp
