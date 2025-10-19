# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

Petruk is a PHP CLI tool that extends Composer functionality for private repositories. It simplifies requiring packages from private Git repositories (both SSH and HTTPS) and local paths by automatically configuring repository settings and handling SSH key management.

## Architecture

### Core Components

- **RequireCommand.php**: Main command class that handles the `require` subcommand
- **helpers.php**: Contains the `run_proc()` utility function for executing shell commands
- **petruk**: Executable entry point that bootstraps the Symfony Console application

### Key Functionality

The tool supports three repository types:
1. **Local path repositories**: Automatically detects local folders with composer.json
2. **SSH Git repositories**: Format `git.server-repo.com:owner/repo` 
3. **HTTPS Git repositories**: Standard HTTPS URLs

The command automatically:
- Creates composer.json if missing
- Configures repository settings in composer.json
- Handles SSH key generation and known_hosts management
- Executes composer require with proper repository configuration

## Development Commands

### Setup
```bash
composer install
```

### Running the Tool
```bash
./petruk require <package_name> [version] [options]
```

### Common Usage Examples
```bash
# Local path package
./petruk require ../my-local-package

# SSH private repository  
./petruk require git.example.com:owner/package dev-main

# HTTPS private repository
./petruk require https://git.example.com/owner/package

# Install as dev dependency
./petruk require package-name --dev

# Global installation
./petruk require package-name --global

# Custom package name override
./petruk require repo-url --paket=custom/package-name
```

## Known Issues

- **PHP 8.2+ Compatibility**: The current code has a return type compatibility issue with Symfony Console 7.x. The `execute()` method needs to return `int` instead of `void`.
- **SSH Key Management**: Tool automatically generates SSH keys and manages known_hosts, which requires proper Git server configuration.

## Development Notes

- Uses Symfony Console, Filesystem, and Process components
- PSR-4 autoloading with namespace `Ratno\Petruk\Console`
- No test suite currently implemented
- Indonesian language used in some error messages and comments