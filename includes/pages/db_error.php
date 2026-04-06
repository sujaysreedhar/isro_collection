<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Unavailable - Database Connection Error</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg: #f8fafc;
            --text: #1e293b;
            --text-muted: #64748b;
            --surface: #ffffff;
            --border: #e2e8f0;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }



        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
            line-height: 1.5;
        }

        .container {
            max-width: 500px;
            width: 100%;
            background: var(--surface);
            padding: 3rem 2rem;
            border-radius: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            text-align: center;
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .icon-box {
            width: 80px;
            height: 80px;
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
        }

        .icon-box svg {
            width: 40px;
            height: 40px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        h1 {
            font-size: 1.875rem;
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -0.025em;
        }

        p {
            color: var(--text-muted);
            font-size: 1.125rem;
            margin-bottom: 2rem;
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 1rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }



        .debug-info {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            text-align: left;
        }

        .debug-info summary {
            font-size: 0.875rem;
            color: var(--text-muted);
            cursor: pointer;
            user-select: none;
            outline: none;
        }

        .debug-info pre {
            margin-top: 1rem;
            background: rgba(0, 0, 0, 0.05);
            padding: 1rem;
            border-radius: 0.5rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            overflow-x: auto;
            color: var(--text-muted);
            white-space: pre-wrap;
            word-break: break-all;
        }


    </style>
</head>
<body>
    <div class="container">
        <div class="icon-box">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
            </svg>
        </div>
        <h1>Database Unavailable</h1>
        <p>Our database is currently performing maintenance or is temporarily unreachable. Please try again in a few moments.</p>
        
        <div class="actions">
            <button onclick="window.location.reload()" class="btn btn-primary">Try Again</button>
        </div>

        <?php if (defined('DEBUG_MODE') && DEBUG_MODE && isset($errorMessage)): ?>
        <details class="debug-info">
            <summary>Technical Details</summary>
            <pre><?= htmlspecialchars($errorMessage) ?></pre>
        </details>
        <?php endif; ?>
    </div>
</body>
</html>
