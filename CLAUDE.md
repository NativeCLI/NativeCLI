# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

NativePHP CLI is a command-line tool for creating and managing Laravel projects with NativePHP integration. It wraps the Laravel installer and adds NativePHP packages (desktop or mobile) to new Laravel projects, then runs the installation process.

## Development Commands

### Testing
```bash
# Run all tests
composer test
# or
vendor/bin/pest

# Run specific test file
vendor/bin/pest tests/CacheTest.php

# Run tests with coverage
vendor/bin/pest --coverage
```

### Code Quality
```bash
# Run PHPStan static analysis
vendor/bin/phpstan analyse

# Run Laravel Pint for code formatting
vendor/bin/pint

# Pint with specific file
vendor/bin/pint src/Application.php
```

### Building
```bash
# The project uses Box to create a PHAR executable
# Configuration is in box.json
```

### Local Development
```bash
# Install dependencies
composer install

# The binary is at bin/nativecli
php bin/nativecli new test-app

# For global testing, symlink or install locally
composer global require nativecli/nativecli
```

## Architecture

### Core Components

**Application Entry Point** (bin/nativecli)
- Bootstrap file that loads autoloader and creates Application instance
- Defines NATIVECLI_HOME_PATH constant pointing to ~/.nativephp

**Application** (src/Application.php)
- Extends Symfony Console Application
- Registers all available commands
- Auto-update system: checks for updates on every command run (unless disabled in config)
- If auto-update is enabled, transparently updates and re-executes the original command

**Commands** (src/Command/*)
Key commands:
- `NewCommand`: Creates Laravel project, installs NativePHP package (desktop or mobile), runs native:install artisan command
- `UpdateNativePHPCommand`: Updates NativePHP packages in an existing project
- `SelfUpdateCommand`: Updates the CLI tool itself
- `CheckNativePHPUpdatesCommand`: Checks for available NativePHP package updates
- `InstallNativePHPMobileCommand`: Installs mobile support in existing project
- `ConfigurationCommand`: Manages CLI configuration
- `ClearCacheCommand`: Clears the CLI cache

**Configuration System** (src/Configuration.php)
- Configuration files: `.nativecli.json`
- Supports both global (in composer home) and local (project directory) configs
- Global config location: `{composer global home}/.nativecli.json`
- CompiledConfiguration merges global and local configs (local takes precedence)
- Key settings:
  - `updates.check`: Whether to check for updates
  - `updates.auto`: Whether to auto-update
  - `append.*`: Additional arguments to append to commands

**Composer Helper** (src/Composer.php)
- Extends Laravel's Composer class
- Provides methods to:
  - Find global composer home directory
  - Require packages (with proper TTY handling)
  - Get package versions from composer.lock
  - Check if packages exist in composer.json
- Uses semantic versioning via z4kn4fein/php-semver

**Cache System** (src/Cache.php)
- Simple JSON-based file cache in cache/ directory
- Cache files: `{key}_cache.json`
- Each cache entry has an 'expires' timestamp (default 3600 seconds)
- Used for storing version information and API responses

**Version Management**
- `src/Version.php`: Manages CLI tool version
- `src/NativePHP.php`: Manages NativePHP package versions
- `PackageVersionRetrieverTrait`: Fetches latest package versions from packagist.org

**Services** (src/Services/*)
- `RepositoryManager`: Manages composer repositories (e.g., adding nativephp.composer.sh for mobile)

### Key Workflows

**Creating a New Project** (NewCommand flow):
1. Passes arguments to Laravel installer to create Laravel project
2. Changes directory into new project
3. Requires appropriate NativePHP package:
   - Desktop: `nativephp/desktop`
   - Mobile: `nativephp/mobile` (adds composer repository first)
4. Runs `php artisan native:install`
5. For mobile: prompts for and validates NATIVEPHP_APP_ID, writes to .env
6. Optionally runs `php artisan native:run` to start the app

**Update Checking** (Application.php):
- Before every command (except self-update), checks if updates are enabled
- Runs `self-update --check --format=json` silently
- If update available and auto-update enabled: updates then re-runs original command
- If update available and auto-update disabled: displays notification

## Testing

- Uses Pest PHP testing framework
- Test bootstrap: tests/Pest.php sets constants (TESTS_ROOT, TESTS_DATA_DIR, ROOT_DIR, SRC_DIR)
- In GitHub Actions: auto-installs nativecli globally before tests
- Test structure:
  - Unit tests for individual classes (Cache, Composer, Configuration, NativePHP, Version)
  - Command tests for CLI commands
  - Integration test for binary execution

## Important Notes

- The tool requires PHP with curl extension
- Uses Symfony Process for all command execution (supports TTY when available)
- Mobile support requires special app ID validation (reverse-DNS format, cannot contain "nativephp")
- Configuration supports string-to-boolean conversion ("true"/"false" strings)
- Rate limiting: catches RateLimitedException (src/Exception/RateLimitedException.php)
- GitHub Actions integration: `.github/workflows/claude.yml` allows MEMBER/OWNER/COLLABORATOR to use @claude mentions
