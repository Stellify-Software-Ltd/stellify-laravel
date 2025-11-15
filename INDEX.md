# Documentation Index

Welcome to the Stellify Laravel package documentation. This index will help you find the information you need.

## Getting Started

📖 **[README.md](README.md)** - Start here  
Main package documentation covering installation, usage, and features.

📋 **[QUICKSTART.md](QUICKSTART.md)** - Fast setup  
Step-by-step guide to get up and running in minutes.

## Understanding the Package

🏗️ **[ARCHITECTURE.md](ARCHITECTURE.md)** - Technical deep dive  
Detailed explanation of package structure, components, data flow, and internals.

📊 **[PROJECT_SUMMARY.md](PROJECT_SUMMARY.md)** - Overview  
High-level summary of what we built, why, and how it works.

## Development

🗺️ **[ROADMAP.md](ROADMAP.md)** - Future plans  
Development phases, planned features, and timeline.

📝 **[CHANGELOG.md](CHANGELOG.md)** - Version history  
Track all changes, additions, and updates to the package.

## Legal

⚖️ **[LICENSE](LICENSE)** - MIT License  
Open source license terms.

---

## Quick Navigation

### For Users
1. Read [README.md](README.md) for overview
2. Follow [QUICKSTART.md](QUICKSTART.md) for installation
3. Run `php artisan stellify:export`
4. Open your project in Stellify

### For Contributors
1. Read [ARCHITECTURE.md](ARCHITECTURE.md) to understand the codebase
2. Check [ROADMAP.md](ROADMAP.md) for upcoming features
3. Review [CHANGELOG.md](CHANGELOG.md) for recent changes
4. Submit PRs for improvements

### For Developers
1. Clone the repository
2. Read [ARCHITECTURE.md](ARCHITECTURE.md) for technical details
3. Check out the `/src` directory structure
4. Review parser implementations in `/src/Parser`

## Package Structure

```
stellify-laravel/
├── Documentation
│   ├── README.md           # Main documentation
│   ├── QUICKSTART.md       # Quick setup guide
│   ├── ARCHITECTURE.md     # Technical details
│   ├── PROJECT_SUMMARY.md  # Project overview
│   ├── ROADMAP.md          # Future plans
│   ├── CHANGELOG.md        # Version history
│   └── INDEX.md            # This file
│
├── Source Code
│   ├── src/Commands/       # Artisan commands
│   ├── src/Parser/         # Parser classes
│   └── src/StellifyServiceProvider.php
│
└── Configuration
    ├── composer.json       # Package manifest
    ├── LICENSE            # MIT license
    └── .gitignore         # Git ignore rules
```

## Key Concepts

### What is Stellify?
Stellify is a collaborative Laravel development platform that stores code as JSON definitions in a database rather than traditional files. This enables real-time collaboration and automatic refactoring.

### What does this package do?
It parses existing Laravel projects locally and exports them into Stellify's database format, eliminating the need for server-side parsing infrastructure.

### How does it work?
1. Uses nikic/php-parser to convert PHP to AST
2. Traverses AST to extract structure
3. Converts to JSON with UUIDs
4. Bulk inserts into Stellify database

### Why build this?
- **Cost**: Eliminates infrastructure costs
- **Speed**: Local parsing is faster
- **Privacy**: Code stays local until uploaded
- **Control**: Users decide what to export

## Command Reference

### Basic Usage
```bash
php artisan stellify:export
```

### Common Options
```bash
--only=routes              # Export only routes
--only=controllers,models  # Export multiple types
--path=app/Services        # Export specific path
--exclude=tests,vendor     # Exclude paths
--connection=stellify      # Database connection
```

### Examples
See [QUICKSTART.md](QUICKSTART.md) for more examples.

## Component Reference

### Parsers
- **PhpFileParser** - Parses PHP files into AST
- **AstVisitor** - Traverses AST nodes
- **RouteParser** - Extracts Laravel routes
- **ConfigParser** - Parses config files
- **DirectoryParser** - Maps directory structure

See [ARCHITECTURE.md](ARCHITECTURE.md) for detailed component documentation.

## Troubleshooting

Common issues and solutions can be found in:
- [README.md](README.md#troubleshooting)
- [QUICKSTART.md](QUICKSTART.md#troubleshooting)

## Support

- **GitHub Issues**: Report bugs and request features
- **Email**: support@stellify.io
- **Documentation**: https://docs.stellify.io

## Contributing

We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

Check [ROADMAP.md](ROADMAP.md) for features we're planning to build.

## Version Information

- **Current Version**: 1.0.0 (unreleased)
- **PHP Requirements**: 8.1+
- **Laravel Requirements**: 10.x, 11.x
- **Dependencies**: nikic/php-parser

See [CHANGELOG.md](CHANGELOG.md) for version history.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

---

**Last Updated**: November 15, 2025  
**Package**: stellify/laravel  
**Maintained by**: Stellify Software Ltd
