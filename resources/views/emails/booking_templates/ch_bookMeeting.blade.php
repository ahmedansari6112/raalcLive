<!DOCTYPE html>
<html lang="zh">
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
        <!-- Admin Email Content in Chinese -->
        <div class="email-section">
            <h1>{{ $bookingDetail['change_status'] ? '会议预订状态已更新' : '预订详情' }}</h1>
            <table>
                <thead>
                    <tr>
                        <th>字段</th>
                        <th>详情</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>客户姓名</td>
                        <td>{{ $bookingDetail['client_name'] }}</td>
                    </tr>
                    <tr>
                        <td>客户邮箱</td>
                        <td>{{ $bookingDetail['client_email'] }}</td>
                    </tr>
                    <tr>
                        <td>客户电话</td>
                        <td>{{ $bookingDetail['client_phone'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td>顾问姓名</td>
                        <td>{{ $bookingDetail['consultant_name'] }}</td>
                    </tr>
                    <tr>
                        <td>顾问职位</td>
                        <td>{{ $bookingDetail['consultant_designation'] }}</td>
                    </tr>
                    <tr>
                        <td>会议日期</td>
                        <td>{{ $bookingDetail['meeting_date'] }}</td>
                    </tr>
                    <tr>
                        <td>时间段</td>
                        <td>{{ $bookingDetail['time_slot'] }}</td>
                    </tr>
                    <tr>
                        <td>参与人数</td>
                        <td>{{ $bookingDetail['number_of_attendees'] }}</td>
                    </tr>
                    <tr>
                        <td>会议目的</td>
                        <td>{{ $bookingDetail['meeting_purpose'] }}</td>
                    </tr>
                    @if(!empty($bookingDetail['description']))
                    <tr>
                        <td>管理员评论</td>
                        <td>{{ $bookingDetail['description'] }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>会议状态</td>
                        <td>{{ $bookingDetail['booking_status'] }}</td>
                    </tr>
                </tbody>
            </table>
            <br>
            <p>此致敬礼,<br>{{ config('app.name') }}</p>
        </div>
    @else
        <!-- User Email Content in Chinese -->
        <div class="email-section">
            <h1>{{ $bookingDetail['change_status'] ? '您的预订状态已更新！' : '感谢您的预订！' }}</h1>
            <p>亲爱的 {{ $bookingDetail['client_name'] }}，</p>
            <p>{{ $bookingDetail['change_status'] ? '您的预订状态已更新。请查看以下详细信息：' : '感谢您预约会议。以下是您的预订详细信息：' }}</p>
            <table>
                <thead>
                    <tr>
                        <th>字段</th>
                        <th>详情</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>顾问姓名</td>
                        <td>{{ $bookingDetail['consultant_name'] }}</td>
                    </tr>
                    <tr>
                        <td>顾问职位</td>
                        <td>{{ $bookingDetail['consultant_designation'] }}</td>
                    </tr>
                    <tr>
                        <td>会议日期</td>
                        <td>{{ $bookingDetail['meeting_date'] }}</td>
                    </tr>
                    <tr>
                        <td>时间段</td>
                        <td>{{ $bookingDetail['time_slot'] }}</td>
                    </tr>
                    <tr>
                        <td>参与人数</td>
                        <td>{{ $bookingDetail['number_of_attendees'] }}</td>
                    </tr>
                    <tr>
                        <td>会议目的</td>
                        <td>{{ $bookingDetail['meeting_purpose'] }}</td>
                    </tr>
                    @if(!empty($bookingDetail['description']))
                    <tr>
                        <td>管理员评论</td>
                        <td>{{ $bookingDetail['description'] }}</td>
                    </tr>
                    @endif
                    @if($bookingDetail['change_status'])
                    <tr>
                        <td>更新后的状态</td>
                        <td>{{ $bookingDetail['booking_status'] }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
            <br>
            <p>请保存这些信息以供记录。</p>
            <p>此致敬礼,<br>{{ config('app.name') }}</p>
        </div>
    @endif
</body>
</html>

