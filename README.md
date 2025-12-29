# WP SignFlow

WordPress plugin for contract management with electronic signature.

## Installation

1. Copy `wp-signflow/` to `/wp-content/plugins/`
2. Activate the plugin in WordPress

That's it! The plugin works immediately.

## Features

- **Contract Templates**: Create templates with dynamic variables `{{variable_name}}`
- **Auto Generation**: PDF generation (FPDF included)
- **Tablet Signature**: Secure signature page with touch support
- **Security**: SHA-256 hash, unique tokens, audit trail
- **Storage**: Local (default) or Google Cloud Storage
- **API**: REST API + PHP helper functions

## Quick Usage

### 1. Create a Template (Admin)

```
SignFlow > Templates > Add New
```

```html
<h1>Service Contract</h1>
<p>Client: <strong>{{client_name}}</strong></p>
<p>Amount: {{amount}}</p>
```

### 2. Generate a Contract (Code)

```php
$result = signflow_generate_contract('my-template', [
    'client_name' => 'John Doe',
    'amount' => '$1,500'
]);

// Send signature link
wp_mail('client@email.com', 'Contract', $result['signature_url']);
```

### 3. Get Contract Info

```php
// Check status
$status = signflow_get_contract_status($contract_id); // 'pending' or 'signed'

// Verify signature integrity
$valid = signflow_verify_signature($contract_id);

// Get audit trail
$audit = signflow_get_audit_trail($contract_id);
```

## REST API

```bash
POST /wp-json/signflow/v1/generate
GET  /wp-json/signflow/v1/contract/{id}
GET  /wp-json/signflow/v1/contract/{id}/status
GET  /wp-json/signflow/v1/contract/{id}/verify
GET  /wp-json/signflow/v1/contract/{id}/audit
```

Authentication: Header `X-SignFlow-API-Key` (generate in Settings)

## Configuration

### Storage
- **Local** (default): Documents in `/wp-content/uploads/wp-signflow/`
- **Google Cloud**: Install `composer require google/cloud-storage`, configure in Settings

### API Key
**SignFlow > Settings** → Generate API Key

## Security

- 64-character unique tokens
- SHA-256 document hashing
- .htaccess protection
- Complete audit trail (IP, date, User Agent)
- Mandatory explicit consent

## Requirements

- PHP 7.4+
- WordPress 5.0+
- Works on shared hosting (no SSH required)


## License

GPL v2 or later
