<!doctype html>
<html>
<head><meta charset="utf-8"><title>Gregwar Captcha</title></head>
<body>
  <h2>Test CAPTCHA (Gregwar)</h2>
  <form action="<?= route('verify.captcha') ?>" method="post">
    <p><img src="<?= $builder->inline(); ?>" alt="captcha"></p>
    <p><input type="text" name="captcha" placeholder="Nhập mã..."></p>
    <button type="submit">Gửi</button>
  </form>
</body>
</html>