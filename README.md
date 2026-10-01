# PawaPay PHP Sandbox Tester

A small standalone PHP application for testing PawaPay Merchant API V2 deposits in the sandbox.

## Requirements

- PHP 8.1+
- PHP cURL extension
- A PawaPay sandbox account
- A PawaPay sandbox API token

PawaPay's sandbox is isolated from production. Generate the sandbox API token from the sandbox dashboard.

## Setup

```bash
cp .env.example .env
nano .env
```

Set:

```env
PAWAPAY_API_TOKEN=YOUR_SANDBOX_TOKEN
PAWAPAY_BASE_URL=https://api.sandbox.pawapay.io
PAWAPAY_COUNTRY=MWI
PAWAPAY_CURRENCY=MWK
```

Then run:

```bash
php -S 127.0.0.1:8080
```

Open:

http://127.0.0.1:8080

## API flow

The application calls:

- `POST /v2/deposits` — initiate a deposit
- `GET /v2/deposits/{depositId}` — check status
- `GET /v2/availability?country=MWI&operationType=DEPOSIT` — inspect provider availability

The deposit request uses:

```json
{
  "amount": "100.00",
  "currency": "MWK",
  "depositId": "UUID",
  "payer": {
    "type": "MMO",
    "accountDetails": {
      "provider": "AIRTEL_MONEY_MWI",
      "phoneNumber": "SANDBOX_TEST_NUMBER"
    }
  },
  "clientReferenceId": "TEST-123",
  "customerMessage": "Sandbox payment test"
}
```

## Important

Use only PawaPay-provided sandbox test MSISDNs. Do not use real customer numbers for sandbox testing.

Never commit `.env` or a production API token to Git.

## Sources

PawaPay Getting Started:
https://www.pawapay.io/getting-started-guide-ai

PawaPay Merchant API V2:
https://www.postman.com/pawapay-4106/pawapay/documentation/f2x5p5r/pawapay-merchant-api-v2
