<?php
declare(strict_types=1);
require_once __DIR__ . '/pawapay.php';

$config = config();
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'deposit') {
            $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
            $phone = trim($_POST['phone'] ?? '');
            $provider = trim($_POST['provider'] ?? '');
            $reference = trim($_POST['reference'] ?? '');
            $message = trim($_POST['message'] ?? 'Test payment');

            if ($amount === false || $amount <= 0) throw new InvalidArgumentException('Enter a valid amount.');
            if ($phone === '') throw new InvalidArgumentException('Enter the mobile-money phone number.');
            if ($provider === '') throw new InvalidArgumentException('Select a provider.');
            if ($reference === '') $reference = 'TEST-' . date('YmdHis');

            $result = createDeposit([
                'amount' => $amount,
                'phone' => $phone,
                'provider' => $provider,
                'reference' => $reference,
                'message' => $message,
            ]);
        } elseif ($action === 'status') {
            $depositId = trim($_POST['deposit_id'] ?? '');
            if ($depositId === '') throw new InvalidArgumentException('Enter a deposit ID.');
            $result = getDeposit($depositId);
        } elseif ($action === 'availability') {
            $result = getAvailability();
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($config['app_name']) ?></title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f4f6f8;margin:0;color:#172033}
.container{max-width:1050px;margin:40px auto;padding:0 20px}
.card{background:#fff;border-radius:14px;padding:24px;margin-bottom:20px;box-shadow:0 4px 18px #00000010}
h1{margin-top:0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
label{font-weight:600;display:block;margin-bottom:6px}
input,select,button{width:100%;box-sizing:border-box;padding:11px;border:1px solid #ccd3dc;border-radius:8px;font-size:15px}
button{background:#1e2a57;color:white;border:0;cursor:pointer;font-weight:700}
button.secondary{background:#52606d}.full{grid-column:1/-1}
.alert{padding:12px;border-radius:8px;margin-bottom:16px;background:#fff3cd}.result{background:#111827;color:#e5e7eb;padding:18px;border-radius:10px;overflow:auto}
small{color:#667085}.status{font-size:18px;font-weight:800}
@media(max-width:700px){.grid{grid-template-columns:1fr}.full{grid-column:auto}}
</style>
</head>
<body>
<div class="container">
<div class="card">
<h1>PawaPay Sandbox Tester</h1>
<p>PHP + cURL · Merchant API V2 · <?= h($config['country']) ?> / <?= h($config['currency']) ?></p>
<small>Sandbox only. Do not place a production token in this application.</small>
</div>

<?php if ($error): ?><div class="alert"><strong>Error:</strong> <?= h($error) ?></div><?php endif; ?>

<div class="card">
<h2>1. Create Deposit</h2>
<form method="post">
<input type="hidden" name="action" value="deposit">
<div class="grid">
<div>
<label>Amount (MWK)</label>
<input name="amount" type="number" min="1" step="0.01" value="100" required>
</div>
<div>
<label>Provider</label>
<select name="provider" required>
<option value="AIRTEL_MWI">AIRTEL_MONEY</option>
<option value="TNM_MWI">TNM_MPAMBA</option>
</select>
</div>
<div>
<label>Phone number</label>
<input name="phone" placeholder="e.g. 265XXXXXXXXX" required>
</div>
<div>
<label>Client reference</label>
<input name="reference" value="TEST-<?= date('YmdHis') ?>">
</div>
<div class="full">
<label>Customer message</label>
<input name="message" maxlength="22" value="Sandbox payment test">
</div>
<div class="full"><button type="submit">Initiate Sandbox Payment</button></div>
</div>
</form>
</div>

<div class="card">
<h2>2. Check Deposit Status</h2>
<form method="post">
<input type="hidden" name="action" value="status">
<label>Deposit ID</label>
<input name="deposit_id" placeholder="UUID returned by PawaPay" required>
<br><br><button type="submit" class="secondary">Check Status</button>
</form>
</div>

<div class="card">
<h2>3. Check Provider Availability</h2>
<form method="post">
<input type="hidden" name="action" value="availability">
<button type="submit" class="secondary">Check MWI Deposit Availability</button>
</form>
</div>

<?php if ($result): ?>
<div class="card">
<h2>API Response</h2>
<?php
$data = $result['data'] ?? [];
if (isset($data['status'])): ?><p class="status">Status: <?= h((string)$data['status']) ?></p><?php endif; ?>
<?php if (isset($data['depositId'])): ?><p><strong>Deposit ID:</strong> <?= h((string)$data['depositId']) ?></p><?php endif; ?>
<p><strong>HTTP:</strong> <?= h((string)($result['http_code'] ?? '')) ?></p>
<pre class="result"><?= h(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
</div>
<?php endif; ?>

<div class="card">
<h2>Sandbox flow</h2>
<ol>
<li>Generate/copy your PawaPay sandbox API token.</li>
<li>Put it in <code>.env</code>.</li>
<li>Initiate a deposit using a PawaPay sandbox test MSISDN.</li>
<li>Save the returned <code>depositId</code>.</li>
<li>Check the deposit status until it reaches a final state.</li>
</ol>
<p><strong>Important:</strong> <code>ACCEPTED</code> means PawaPay accepted the request for processing; it does not mean the customer's payment is completed.</p>
</div>
</div>
</body>
</html>
