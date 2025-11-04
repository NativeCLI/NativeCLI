# NativePHP CLI

NativePHP CLI is a command-line tool to create and manage Laravel projects with NativePHP integration.

## Installation

To install NativePHP CLI, you need to have PHP and Composer installed on your system.

```sh
composer global require nativecli/nativecli
```

## Usage

### Create a New Project
To create a new Laravel project with NativePHP, use the new command:
```bash
nativecli new <project-name>
```

### View Logs
Display logs from Laravel and native layers with the logs command:
```bash
# Display last 50 log entries
nativecli logs

# Follow logs in real-time (like tail -f)
nativecli logs --follow

# Filter by log level
nativecli logs --level=error

# Limit number of lines
nativecli logs --lines=100

# Filter by source (laravel, native, all)
nativecli logs --source=laravel

# Filter by date range
nativecli logs --start-date="2025-01-01" --end-date="2025-01-15"

# Combine options
nativecli logs --level=error --lines=20 --follow
```

The `logs` command automatically detects log locations for both development and production environments:
- Development: `storage/logs/laravel.log`
- Production (Desktop): Platform-specific appdata directories based on your `NATIVEPHP_APP_ID`

## Documentation & Command References
For more detailed documentation, visit [NativeCLI Documentation](https://docs.nativecli.com).

## Sponsor
We welcome sponsorships which help us to continue providine Free & Open-Source software and tools to you!

[![GitHub Sponsors](https://img.shields.io/badge/Sponsor-GitHub-green?logo=github)](https://github.com/sponsors/PeteBishwhip)

### With thanks to our current sponsors...

- [Simon Hamp](https://github.com/simonhamp) - Co-Creator of [NativePHP](https://github.com/NativePHP) 


## License
This project is licensed under the MIT License.
