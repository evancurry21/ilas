# ILAS Custom Modules

This directory contains custom Drupal modules developed specifically for the Idaho Legal Aid Services website.

## Modules Overview

### 1. ILAS Chatbot (`ilas_chatbot`)
**Purpose**: Integrates Google Dialogflow to provide automated legal assistance through a conversational interface.

**Key Features**:
- Secure webhook endpoint with authentication
- Dynamic form loading within chat
- Multi-language support
- Configurable UI and positioning

**Documentation**: [Full Documentation](ilas_chatbot/README.md)

### 2. ILAS Hotspot (`ilas_hotspot`)
**Purpose**: Creates interactive hotspot overlays on images to display information about different legal service areas.

**Key Features**:
- Configurable hotspot positions and content
- Responsive design with mobile support
- Lazy loading for performance
- Analytics tracking capability

**Usage**: Place the hotspot block and configure through admin UI at `/admin/config/content/ilas-hotspot`

### 3. ILAS Resources (`ilas_resources`)
**Purpose**: Provides enhanced functionality for legal resource content management and filtering.

**Key Features**:
- Custom Views plugins for topic filtering
- Service area taxonomy integration
- Optimized database queries with batch processing
- Dynamic resource filtering by topic

**Components**:
- `CurrentServiceTid`: Views argument plugin for service area context
- `StrictTopicServiceArea`: Views filter for topic-service area mapping

## Installation

Enable all custom modules:
```bash
drush en ilas_chatbot ilas_hotspot ilas_resources -y
```

## Development Guidelines

### Coding Standards

All modules follow Drupal coding standards:
- PSR-4 autoloading
- Drupal 8+ plugin system
- Dependency injection where applicable
- Comprehensive PHPDoc comments

### Security

- All user input is sanitized
- CSRF protection on forms and endpoints
- Access control checks implemented
- XSS prevention in all output

### Performance

- Database queries optimized with bulk loading
- Caching implemented where appropriate
- Lazy loading for heavy content
- Asset aggregation supported

## Testing

Run all custom module tests:
```bash
./vendor/bin/phpunit web/modules/custom/
```

Run specific module tests:
```bash
./vendor/bin/phpunit web/modules/custom/ilas_chatbot/tests/
./vendor/bin/phpunit web/modules/custom/ilas_resources/tests/
./vendor/bin/phpunit web/modules/custom/ilas_hotspot/tests/
```

## Configuration Management

All modules support configuration export/import:

Export configuration:
```bash
drush config:export
```

Import configuration:
```bash
drush config:import
```

## Dependencies

- Drupal 10.1+ or 11.0+
- PHP 8.1+
- For chatbot: Google Dialogflow account
- For resources: Taxonomy module enabled

## Troubleshooting

### Common Issues

1. **Module not found**: Clear cache with `drush cr`
2. **Configuration not saving**: Check file permissions
3. **JavaScript errors**: Verify library dependencies
4. **Database errors**: Run updates with `drush updb`

### Debug Mode

Enable debug mode for detailed logging:
```php
$config['system.logging']['error_level'] = 'verbose';
```

## Contributing

1. Create feature branch from `master`
2. Follow Drupal coding standards
3. Add tests for new functionality
4. Update documentation
5. Submit merge request

## Module Interactions

The modules can work together:
- Chatbot can link to resources
- Hotspots can trigger chatbot
- Resources filtered by service areas

## Future Enhancements

- GraphQL API for resources
- Machine learning for chat responses
- Advanced analytics dashboard
- Multi-site support

## Support

For issues or questions:
1. Check module documentation
2. Review Drupal logs
3. Contact development team

## License

All custom modules are part of the ILAS project and follow the same licensing terms.