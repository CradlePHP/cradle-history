## History

Cradle history logs actions made by users.

## Install

```
composer install cradlephp/cradle-history
```

### Usage

Place this in any route or event.

```php
$this->log('<MESSAGE>', $request, $response);
```

### History Routes

The following routes are available in the admin.

 - `GET /admin/history/search` - history search page
 - `GET /admin/history/:action/logs` - Restores a history
 - `GET /admin/history/export/:type` - Export history data

### History Events

 - `history-create`
 - `history-detail`
 - `history-remove`
 - `history-restore`
 - `history-update`
 - `history-export`
 - `history-mark-as-read`
