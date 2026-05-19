# List all users

Returns a paginated list of all users in the system.
This example shows namespace-based path: `docs/api/Api/V1/UserController/index.md`

## Supported Formats

For controller: `App\Http\Controllers\Api\V1\UserController@index`

The extension will search these paths (in order):
1. `docs/api/Api/V1/UserController/index.md` ← this file
2. `docs/api/V1/UserController/index.md`
3. `docs/api/UserController/index.md`
