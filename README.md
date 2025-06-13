# Kiddo Project

## Overview
This is a PHP-based application for managing workshops and email communications.

## Features
- Workshop management
- Email processing and import functionality
- Multi-language support (including Polish)

## TODO List

### High Priority
- [ ] Complete workshop management functionality
- [ ] Complete booking process
- [ ] Add workflows of payment and booking
- [x] Complete user login/register
- [ ] Cover logging in non-existing user 

### Medium Priority
- [ ] Write comprehensive tests
- [ ] Add more language translations

### Low Priority
- [ ] Add CD deploy pipeline
- [ ] Set up monitoring
- [ ] Add logging

## Development Setup
1. Clone the repository
2. Install dependencies `docker compose run php composer install`
3. Install tailwind `docker compose run php bin/console tailwind:build`
4. Run the application `docker compose up`
5. Create database schema `docker compose run php bin/console doctrine:schema:update --force`

## Contributing
Contributions are welcome! Please open an issue or submit a pull request.