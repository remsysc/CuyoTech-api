<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $frontendUrl = (string) config('app.frontend_url', 'http://localhost:3000');

    if ($request->wantsJson()) {
        return response()->json([
            'service' => 'CuyoTech SSIS API',
            'status' => 'operational',
            'frontend_url' => $frontendUrl,
            'health_url' => url('/up'),
        ]);
    }

    $safeFrontendUrl = htmlspecialchars($frontendUrl, ENT_QUOTES, 'UTF-8');
    $safeHealthUrl = htmlspecialchars(url('/up'), ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CuyoTech SSIS API</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 24px;
        }
        .card {
            max-width: 480px;
            width: 100%;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4);
            text-align: center;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.3);
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
        }
        h1 {
            margin: 0 0 8px 0;
            font-size: 22px;
            font-weight: 700;
        }
        p {
            margin: 0 0 24px 0;
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            padding: 12px 24px;
            border-radius: 10px;
            transition: background 0.15s ease;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .meta {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #334155;
            font-size: 12px;
            color: #64748b;
        }
        .meta a {
            color: #93c5fd;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge"><span class="dot"></span> API Server Online</div>
        <h1>CuyoTech SSIS Backend API</h1>
        <p>The Laravel REST API is running on port 8000. To access the Student Services Information System portal, open the React frontend on port 3000.</p>
        <a class="btn" href="{$safeFrontendUrl}">Open CuyoTech SSIS Portal ({$safeFrontendUrl})</a>
        <div class="meta">
            Healthcheck: <a href="{$safeHealthUrl}">/up</a> &bull; Endpoints: <code>/api/*</code>
        </div>
    </div>
</body>
</html>
HTML;

    return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
})->name('home');

Route::get('/receipts/{or_number}', [ReceiptController::class, 'show'])->where('or_number', '.*');
