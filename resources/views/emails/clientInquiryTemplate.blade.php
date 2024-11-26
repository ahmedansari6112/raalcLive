<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            direction: ltr;
            text-align: left;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #f4f4f4;
        }
        .email-section {
            margin-bottom: 40px;
        }
    </style>
</head>
<body>
    @if($isAdmin)
        <!-- Admin Email Content -->
        <div class="email-section">
            <h1>Client Request Detail</h1>
            <table>
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Client Name</td>
                        <td>{{ $inquiryDetail['client_name'] }}</td>
                    </tr>
                    <tr>
                        <td>Client Email</td>
                        <td>{{ $inquiryDetail['client_email'] }}</td>
                    </tr>
                    <tr>
                        <td>Message</td>
                        <td>{{ $inquiryDetail['message'] }}</td>
                    </tr>
                </tbody>
            </table>
            <br>
            <p>Regards,<br>{{ config('app.name') }}</p>
        </div>
    @endif
</body>
</html>