FROM webdevops/php-apache:8.2

# Copy your project files to the container
COPY . /app

# Set the working directory
WORKDIR /app

# Install any additional system packages or PHP extensions you need
# Example: Install the PHP extension "gd"
# RUN docker-php-ext-install gd
RUN composer install

# Expose the port that Apache listens on
EXPOSE 80
