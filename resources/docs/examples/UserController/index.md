# List all users

Returns a paginated list of all users in the system.
Supports filtering by status and role.

## Query Parameters

- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15, max: 100)
- `status` - Filter by status: active, inactive, suspended
- `role` - Filter by role: admin, user, moderator

## Notes

Results are ordered by creation date (newest first).
