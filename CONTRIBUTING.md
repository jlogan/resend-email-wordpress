# Contributing & Development Guidelines

## Version Management

This plugin follows [Semantic Versioning](https://semver.org/):
- **MAJOR** version (1.x.x) - Breaking changes
- **MINOR** version (x.1.x) - New features, backwards compatible
- **PATCH** version (x.x.1) - Bug fixes, backwards compatible

### Current Version: 1.0.0

When making changes:
1. Update version in `resend-email-integration.php` plugin header
2. Update `Stable tag` in `.wordpress-org/README.md` and root `README.md`
3. Add changelog entry in `.wordpress-org/README.md`
4. Create descriptive commit messages (see below)

## Commit Message Standards

All commits should follow this format:

```
Short summary (50 chars or less)

More detailed explanation if needed. Wrap at 72 characters.
Explain what and why, not how.

- Bullet point for major changes
- Another bullet point if needed
```

### Examples:

**Feature Addition:**
```
Add email template preview functionality

- Add preview button in email logs table
- Display rendered HTML in modal with iframe
- Cache template data for faster loading
```

**Bug Fix:**
```
Fix timezone conversion in email logs

- Use get_date_from_gmt() for proper UTC to local conversion
- Handle both MySQL datetime and ISO 8601 formats
- Fixes issue where emails showed future timestamps
```

**Code Quality:**
```
Refactor database queries for better performance

- Add proper indexing to email cache table
- Use prepared statements consistently
- Add query result caching where appropriate
```

## Code Quality Standards

- Follow WordPress Coding Standards
- All code must pass WordPress.org Plugin Checker
- Use proper escaping and sanitization
- Add phpcs:ignore comments only when necessary with explanation
- Keep functions focused and single-purpose
- Document complex logic with inline comments

## Testing Checklist

Before committing:
- [ ] Code passes WordPress.org Plugin Checker
- [ ] No PHP errors or warnings
- [ ] Tested on PHP 8.1+
- [ ] Tested on WordPress 5.0+
- [ ] All strings are translatable
- [ ] Security best practices followed

## Portfolio Considerations

This plugin serves as a portfolio piece, so:
- Code should be clean, well-documented, and maintainable
- Commit history should tell a clear story of development
- README and documentation should be professional
- Follow WordPress best practices and community standards

