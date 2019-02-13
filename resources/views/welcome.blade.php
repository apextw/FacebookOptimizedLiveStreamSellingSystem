<!DOCTYPE html>
<html>
<head>
    <title>Facebook Login JavaScript Example</title>
    <meta charset="UTF-8">
</head>
<body>
<form action="/api/payments/2" method="POST">
    @csrf()
    <input type="hidden" name="cmd" value="_xclick" />
                <input type="checkbox" value="17" name="order_id[]">
    <input type="checkbox" value="18" name="order_id[]">
    <input type="checkbox" value="19" name="order_id[]">
    <input type="hidden" value="https://7297f315.ngrok.io/" name="ClintBackURL">
    <button type="submit">Submit</button>
</form>
</body>
</html>
