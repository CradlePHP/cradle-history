# Cradle History Package 

history manager.

## Install

```
composer install cradlephp/cradle-history
```

## History

Cradle history handles everything about the history. It is based on CradlePHP/cradle-system. 

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

### History Example

```PHP
$this->log(
	< MESSAGE >,
	$request,
	$response
);
```