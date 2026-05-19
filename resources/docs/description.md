# API Documentation

Welcome to the API documentation. This document provides a comprehensive overview of all available endpoints, request/response formats, and authentication requirements.

## Authentication

All API endpoints require authentication unless otherwise noted. Include your API token in the `Authorization` header:

```
Authorization: Bearer {your-api-token}
```

## Response Format

All responses are returned in JSON format with the following structure:

```json
{
    "data": {},
    "message": "Success",
    "success": true,
    "code": 200
}
```

## Error Handling

When an error occurs, the API will return an appropriate HTTP status code along with an error message:

| Status Code | Description |
|-------------|-------------|
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Internal Server Error |
