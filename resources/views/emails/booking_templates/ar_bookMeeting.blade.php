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
            <h1>{{ $bookingDetail['change_status'] ? 'تم تحديث حالة طلب الاستشارة القانونية' : 'تفاصيل طلب الاستشارة القانونية' }}</h1>
            <table>
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
                        <td>التاريخ المفضل</td>
                        <td>{{ $bookingDetail['meeting_date'] }}</td>
                    </tr>
                    <tr>
                        <td>الوقت المفضل</td>
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
                        <td>حالة طلب</td>
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
            <h1>{{ $bookingDetail['change_status'] ? 'لقد تم تحديث حالة طلب الاستشارة القانونية الخاص بك!' : 'شكراً لتقديم طلبك!' }}</h1>
            <p>السيد/ة {{ $bookingDetail['client_name'] }}،</p>
            <p>{{ $bookingDetail['change_status'] ? 'لقد تم تحديث حالة طلب الاستشارة القانونية الخاص بك. يرجى الاطلاع على التفاصيل أدناه:' : 'شكراً لتقديم طلب للحصول على استشارة قانونية من مكتب RAALC للمحاماة. سيتواصل فريقنا معك خلال وقت قصير لتأكيد موعد الاجتماع.' }}</p>
            <table>
                <tbody>
                    @if(!empty($bookingDetail['consultant_name']))
                    <tr>
                        <td>إسم الإستشاري</td>
                        <td>{{ $bookingDetail['consultant_name'] }}</td>
                    </tr>
                    @endif
                    @if(!empty($bookingDetail['consultant_designation']))
                    <tr>
                        <td>تسمية المستشار</td>
                        <td>{{ $bookingDetail['consultant_designation'] }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>التاريخ المفضل</td>
                        <td>{{ $bookingDetail['meeting_date'] }}</td>
                    </tr>
                    <tr>
                        <td>الوقت المفضل</td>
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
                        <td>حالة طلب</td>
                        <td>{{ $bookingDetail['booking_status'] }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
            <br>
            <p>يرجى الاحتفاظ بهذه المعلومات لسجلاتك.</p>

            <p>مع التحية،<br> {{ config('app.name') }}</p>
        </div>
    @endif
</body>
</html>

