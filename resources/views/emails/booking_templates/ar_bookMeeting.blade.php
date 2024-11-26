<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            direction: rtl;
            text-align: right;
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
        <!-- Admin Email Content in Arabic -->
        <div class="email-section">
            <h1>{{ $bookingDetail['change_status'] ? 'تم تحديث حالة الحجز' : 'تفاصيل الحجز' }}</h1>
            <table>
                <thead>
                    <tr>
                        <th>الحقل</th>
                        <th>التفاصيل</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>اسم العميل</td>
                        <td>{{ $bookingDetail['client_name'] }}</td>
                    </tr>
                    <tr>
                        <td>بريد العميل الإلكتروني</td>
                        <td>{{ $bookingDetail['client_email'] }}</td>
                    </tr>
                    <tr>
                        <td>رقم هاتف العميل</td>
                        <td>{{ $bookingDetail['client_phone'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td>تاريخ الاجتماع</td>
                        <td>{{ $bookingDetail['meeting_date'] }}</td>
                    </tr>
                    <tr>
                        <td>الوقت</td>
                        <td>{{ $bookingDetail['time_slot'] }}</td>
                    </tr>
                    <tr>
                        <td>عدد الحضور</td>
                        <td>{{ $bookingDetail['number_of_attendees'] }}</td>
                    </tr>
                    <tr>
                        <td>غرض الاجتماع</td>
                        <td>{{ $bookingDetail['meeting_purpose'] }}</td>
                    </tr>
                    @if(!empty($bookingDetail['description']))
                    <tr>
                        <td>تعليق المشرف</td>
                        <td>{{ $bookingDetail['description'] }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>حالة الاجتماع</td>
                        <td>{{ $bookingDetail['booking_status'] }}</td>
                    </tr>
                </tbody>
            </table>
            <br>
            <p>مع التحية،<br>{{ config('app.name') }}</p>
        </div>
    @else
        <!-- User Email Content in Arabic -->
        <div class="email-section">
            <h1>{{ $bookingDetail['change_status'] ? 'تم تحديث حالة الحجز الخاص بك!' : 'شكرًا لحجزك!' }}</h1>
            <p>عزيزي {{ $bookingDetail['client_name'] }}،</p>
            <p>{{ $bookingDetail['change_status'] ? 'تم تحديث حالة الحجز الخاص بك. يرجى الاطلاع على التفاصيل أدناه:' : 'شكرًا لحجز اجتماع معنا. يرجى مراجعة تفاصيل الحجز أدناه:' }}</p>
            <table>
                <thead>
                    <tr>
                        <th>الحقل</th>
                        <th>التفاصيل</th>
                    </tr>
                </thead>
                <tbody>
                    
                    <tr>
                        <td>تاريخ الاجتماع</td>
                        <td>{{ $bookingDetail['meeting_date'] }}</td>
                    </tr>
                    <tr>
                        <td>الوقت</td>
                        <td>{{ $bookingDetail['time_slot'] }}</td>
                    </tr>
                    <tr>
                        <td>عدد الحضور</td>
                        <td>{{ $bookingDetail['number_of_attendees'] }}</td>
                    </tr>
                    <tr>
                        <td>غرض الاجتماع</td>
                        <td>{{ $bookingDetail['meeting_purpose'] }}</td>
                    </tr>
                    @if(!empty($bookingDetail['description']))
                    <tr>
                        <td>تعليق المشرف</td>
                        <td>{{ $bookingDetail['description'] }}</td>
                    </tr>
                    @endif
                    @if($bookingDetail['change_status'])
                    <tr>
                        <td>الحالة المحدثة</td>
                        <td>{{ $bookingDetail['booking_status'] }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
            <br>
            <p>يرجى الاحتفاظ بهذه المعلومات لسجلاتك.</p>

            <p>مع التحية،<br>{{ config('app.name') }}</p>
        </div>
    @endif
</body>
</html>

