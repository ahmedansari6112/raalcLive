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
            <h1>{{ $bookingDetail['change_status'] ? 'Booking Status Updated' : 'Booking Details' }}</h1>
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
                        <td>{{ $bookingDetail['client_name'] }}</td>
                    </tr>
                    <tr>
                        <td>Client Email</td>
                        <td>{{ $bookingDetail['client_email'] }}</td>
                    </tr>
                    <tr>
                        <td>Client Phone</td>
                        <td>{{ $bookingDetail['client_phone'] ?? '' }}</td>
                    </tr>
                    @if(!empty($bookingDetail['consultant_name']))
                    <tr>
                        <td>Consultant Name</td>
                        <td>{{ $bookingDetail['consultant_name'] }}</td>
                    </tr>
                    @endif
                    @if(!empty($bookingDetail['consultant_designation']))
                    <tr>
                        <td>Consultant Designation</td>
                        <td>{{ $bookingDetail['consultant_designation'] }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Meeting Date</td>
                        <td>{{ $bookingDetail['meeting_date'] }}</td>
                    </tr>
                    <tr>
                        <td>Time Slot</td>
                        <td>{{ $bookingDetail['time_slot'] }}</td>
                    </tr>
                    <tr>
                        <td>Number of Attendees</td>
                        <td>{{ $bookingDetail['number_of_attendees'] }}</td>
                    </tr>
                    <tr>
                        <td>Meeting Purpose</td>
                        <td>{{ $bookingDetail['meeting_purpose'] }}</td>
                    </tr>
                    @if(!empty($bookingDetail['description']))
                    <tr>
                        <td>Admin Comment</td>
                        <td>{{ $bookingDetail['description'] }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Meeting Status</td>
                        <td>{{ $bookingDetail['booking_status'] }}</td>
                    </tr>
                </tbody>
            </table>
            <br>
            <p>Regards,<br>{{ config('app.name') }}</p>
        </div>
    @else
        <!-- User Email Content in Table Form -->
        <div class="email-section">
            <h1>{{ $bookingDetail['change_status'] ? 'Your Booking Status Has Been Updated!' : 'Thank You for Your Booking!' }}</h1>
            <p>Dear {{ $bookingDetail['client_name'] }},</p>
            <p>{{ $bookingDetail['change_status'] ? 'Your booking status has been updated. Please see the details below:' : 'Thank you for booking a meeting with us. Please find the details of your booking below:' }}</p>
            <table>
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @if(!empty($bookingDetail['consultant_name']))
                    <tr>
                        <td>Consultant Name</td>
                        <td>{{ $bookingDetail['consultant_name'] }}</td>
                    </tr>
                    @endif
                    @if(!empty($bookingDetail['consultant_designation']))
                    <tr>
                        <td>Consultant Designation</td>
                        <td>{{ $bookingDetail['consultant_designation'] }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Meeting Date</td>
                        <td>{{ $bookingDetail['meeting_date'] }}</td>
                    </tr>
                    <tr>
                        <td>Time Slot</td>
                        <td>{{ $bookingDetail['time_slot'] }}</td>
                    </tr>
                    <tr>
                        <td>Number of Attendees</td>
                        <td>{{ $bookingDetail['number_of_attendees'] }}</td>
                    </tr>
                    <tr>
                        <td>Meeting Purpose</td>
                        <td>{{ $bookingDetail['meeting_purpose'] }}</td>
                    </tr>
                    @if(!empty($bookingDetail['description']))
                    <tr>
                        <td>Admin Comment</td>
                        <td>{{ $bookingDetail['description'] }}</td>
                    </tr>
                    @endif
                    @if($bookingDetail['change_status'])
                    <tr>
                        <td>Updated Status</td>
                        <td>{{ $bookingDetail['booking_status'] }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
            <br>
            <p>Please keep this information for your records.</p>
            <p>Regards,<br>{{ config('app.name') }}</p>
        </div>
    @endif
</body>
</html>
