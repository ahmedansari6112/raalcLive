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

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        th {
            background-color: #f4f4f4;
        }
    </style>
</head>

<body>
    <h1>{{$data['language'] == 'en' ? "Quote Confirmation" : ($data['language'] == 'ar' ? "تأكيد الاقتباس" : ($data['language'] == 'ch' ? "报价确认" : "Подтверждение цены"))}}</h1>

    <table>
        <thead>
            <tr>
                <th>{{ $data['language'] == 'en' ? "Field" : ($data['language'] == 'ar' ? "مجال" : ($data['language'] == 'ch' ? "字段" : "Поле")) }}</th>
                <th>{{ $data['language'] == 'en' ? "Details" : ($data['language'] == 'ar' ? "التفاصيل" : ($data['language'] == 'ch' ? "详情" : "Детали")) }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $data['language'] == 'en' ? "Client Name" : ($data['language'] == 'ar' ? "اسم العميل" : ($data['language'] == 'ch' ? "客户姓名" : "Имя клиента")) }}</td>
                <td>{{$data['name']}}</td>
            </tr>
            <tr>
                <td>{{ $data['language'] == 'en' ? "Client Email" : ($data['language'] == 'ar' ? "بريد العميل الإلكتروني" : ($data['language'] == 'ch' ? "客户电子邮件" : "Электронная почта клиента")) }}</td>
                <td>{{$data['email']}}</td>
            </tr>
            <tr>
                <td>{{ $data['language'] == 'en' ? "Client Phone" : ($data['language'] == 'ar' ? "هاتف العميل" : ($data['language'] == 'ch' ? "客户电话" : "Телефон клиента")) }}</td>
                <td>{{$data['phone']}}</td>
            </tr>
            <tr>
                <td>{{ $data['language'] == 'en' ? "Client Message" : ($data['language'] == 'ar' ? "رسالة العميل" : ($data['language'] == 'ch' ? "客户留言" : "Сообщение клиента")) }}</td>
                <td>{{$data['message']}}</td>
            </tr>

        </tbody>
    </table>
    <br>
    
    <p>{{ $data['language'] == 'en' ? "Please keep this information for your records." : ($data['language'] == 'ar' ? "يرجى الاحتفاظ بهذه المعلومات لسجلاتك." : ($data['language'] == 'ch' ? "请保存这些信息以供记录。" : "Пожалуйста, сохраните эту информацию для ваших записей.")) }}</p>

    <p>{{ $data['language'] == 'en' ? "Regards," : ($data['language'] == 'ar' ? "مع التحية," : ($data['language'] == 'ch' ? "此致," : "С уважением,")) }}<br>{{ config('app.name') }}</p>
</body>

</html>