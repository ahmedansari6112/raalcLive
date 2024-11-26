<!DOCTYPE html>
<html lang="ru">
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
        <!-- Admin Email Content in Russian -->
        <div class="email-section">
            <h1>{{ $bookingDetail['change_status'] ? 'Статус бронирования обновлен' : 'Детали бронирования' }}</h1>
            <table>
                <thead>
                    <tr>
                        <th>Поле</th>
                        <th>Детали</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Имя клиента</td>
                        <td>{{ $bookingDetail['client_name'] }}</td>
                    </tr>
                    <tr>
                        <td>Email клиента</td>
                        <td>{{ $bookingDetail['client_email'] }}</td>
                    </tr>
                    <tr>
                        <td>Телефон клиента</td>
                        <td>{{ $bookingDetail['client_phone'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td>Имя консультанта</td>
                        <td>{{ $bookingDetail['consultant_name'] }}</td>
                    </tr>
                    <tr>
                        <td>Должность консультанта</td>
                        <td>{{ $bookingDetail['consultant_designation'] }}</td>
                    </tr>
                    <tr>
                        <td>Дата встречи</td>
                        <td>{{ $bookingDetail['meeting_date'] }}</td>
                    </tr>
                    <tr>
                        <td>Время встречи</td>
                        <td>{{ $bookingDetail['time_slot'] }}</td>
                    </tr>
                    <tr>
                        <td>Количество участников</td>
                        <td>{{ $bookingDetail['number_of_attendees'] }}</td>
                    </tr>
                    <tr>
                        <td>Цель встречи</td>
                        <td>{{ $bookingDetail['meeting_purpose'] }}</td>
                    </tr>
                    @if(!empty($bookingDetail['description']))
                    <tr>
                        <td>Комментарий администратора</td>
                        <td>{{ $bookingDetail['description'] }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Статус встречи</td>
                        <td>{{ $bookingDetail['booking_status'] }}</td>
                    </tr>
                </tbody>
            </table>
            <br>
            <p>С уважением,<br>{{ config('app.name') }}</p>
        </div>
    @else
        <!-- User Email Content in Russian -->
        <div class="email-section">
            <h1>{{ $bookingDetail['change_status'] ? 'Ваш статус бронирования был обновлен!' : 'Спасибо за ваше бронирование!' }}</h1>
            <p>Уважаемый(ая) {{ $bookingDetail['client_name'] }},</p>
            <p>{{ $bookingDetail['change_status'] ? 'Ваш статус бронирования был обновлен. Пожалуйста, ознакомьтесь с деталями ниже:' : 'Спасибо за бронирование встречи у нас. Пожалуйста, посмотрите детали вашего бронирования ниже:' }}</p>
            <table>
                <thead>
                    <tr>
                        <th>Поле</th>
                        <th>Детали</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Имя консультанта</td>
                        <td>{{ $bookingDetail['consultant_name'] }}</td>
                    </tr>
                    <tr>
                        <td>Должность консультанта</td>
                        <td>{{ $bookingDetail['consultant_designation'] }}</td>
                    </tr>
                    <tr>
                        <td>Дата встречи</td>
                        <td>{{ $bookingDetail['meeting_date'] }}</td>
                    </tr>
                    <tr>
                        <td>Время встречи</td>
                        <td>{{ $bookingDetail['time_slot'] }}</td>
                    </tr>
                    <tr>
                        <td>Количество участников</td>
                        <td>{{ $bookingDetail['number_of_attendees'] }}</td>
                    </tr>
                    <tr>
                        <td>Цель встречи</td>
                        <td>{{ $bookingDetail['meeting_purpose'] }}</td>
                    </tr>
                    @if(!empty($bookingDetail['description']))
                    <tr>
                        <td>Комментарий администратора</td>
                        <td>{{ $bookingDetail['description'] }}</td>
                    </tr>
                    @endif
                    @if($bookingDetail['change_status'])
                    <tr>
                        <td>Обновленный статус</td>
                        <td>{{ $bookingDetail['booking_status'] }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
            <br>
            <p>Пожалуйста, сохраните эту информацию для ваших записей.</p>
            <p>С уважением,<br>{{ config('app.name') }}</p>
        </div>
    @endif
</body>
</html>

