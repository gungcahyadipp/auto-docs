# Create a new user

Creates a new user account in the system. The user will receive
a verification email after successful registration.

## Business Rules

- Email must be unique across the system
- Password must meet complexity requirements (min 8 chars, 1 uppercase, 1 number)
- Users are created with 'pending' status by default
- A welcome email is sent asynchronously after creation

## Rate Limiting

This endpoint is rate-limited to 5 requests per minute per IP.
