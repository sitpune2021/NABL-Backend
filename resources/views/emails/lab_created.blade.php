<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Lab Created</title>
</head>

<body style="font-family: Arial, sans-serif; background:#f4f6f8; padding:20px;">

    <div style="max-width:700px; margin:auto; background:#ffffff; padding:20px; border-radius:8px;">

        <h2 style="color:#2c3e50;">🎉 Lab Created Successfully</h2>

        <p><strong>Lab Name:</strong> {{ $data['lab_name'] }}</p>
        <p><strong>Total Locations:</strong> {{ $data['location_count'] }}</p>
        <p><strong>User Limit:</strong> {{ $data['user_limit'] }}</p>

        <hr>

        <h3 style="color:#34495e;">👤 Super Admin Credentials</h3>

        <table width="100%" cellpadding="8" style="border-collapse: collapse;">
            <tr style="background:#ecf0f1;">
                <th align="left">Email</th>
                <th align="left">Password</th>
            </tr>
            <tr>
                <td>{{ $data['super_admin']['email'] }}</td>
                <td>superadmin1234</td>
            </tr>
        </table>

        <hr>

        <h3 style="color:#34495e;">📍 Location Users</h3>

        <table width="100%" cellpadding="8" style="border-collapse: collapse;">
            <tr style="background:#ecf0f1;">
                <th align="left">Location</th>
                <th align="left">Email</th>
                <th align="left">Password</th>
            </tr>

            @foreach ($data['locations'] as $loc)
                <tr>
                    <td>{{ $loc['location_name'] }}</td>
                    <td>{{ $loc['email'] }}</td>
                    <td>admin123</td>
                </tr>
            @endforeach
        </table>

        <br>

        <p style="font-size:12px; color:#7f8c8d;">
            ⚠️ For security, please change your password after first login.
        </p>
        <a href="{{ config('app.url') }}"
            style="display:inline-block; padding:10px 15px; background:#3498db; color:#fff; text-decoration:none; border-radius:5px;">
            Login Now
        </a>
    </div>

</body>

</html>
