<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تسجيل الدخول - Barmagly Tasks</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📋</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Tajawal',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;
            background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#312e81 100%);padding:20px}
        .login-container{width:100%;max-width:420px;animation:slideUp .6s ease}
        @keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
        .login-card{background:rgba(255,255,255,.05);backdrop-filter:blur(20px);border-radius:24px;padding:40px;
            border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 50px rgba(0,0,0,.3)}
        .logo{text-align:center;margin-bottom:36px}
        .logo-icon{width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#6366f1,#8b5cf6);
            display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;color:#fff;
            box-shadow:0 10px 30px rgba(99,102,241,.4)}
        .logo h1{font-size:28px;font-weight:800;color:#fff;margin-bottom:6px}
        .logo p{color:rgba(255,255,255,.5);font-size:14px}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;color:rgba(255,255,255,.7);font-size:13px;margin-bottom:8px;font-weight:500}
        .form-group label i{margin-left:6px;font-size:12px}
        .form-group input{width:100%;padding:14px 16px;border-radius:12px;border:1px solid rgba(255,255,255,.1);
            background:rgba(255,255,255,.05);color:#fff;font-family:'Tajawal';font-size:15px;transition:all .3s;outline:none}
        .form-group input:focus{border-color:#6366f1;background:rgba(99,102,241,.1);box-shadow:0 0 0 3px rgba(99,102,241,.2)}
        .form-group input::placeholder{color:rgba(255,255,255,.3)}
        .btn{width:100%;padding:14px;border-radius:12px;border:none;font-family:'Tajawal';font-size:16px;font-weight:700;
            cursor:pointer;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:10px}
        .btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;
            box-shadow:0 4px 15px rgba(99,102,241,.4)}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(99,102,241,.5)}
        .btn-primary:active{transform:translateY(0)}
        .error-msg{color:#ef4444;font-size:13px;margin-top:16px;text-align:center;padding:10px;
            background:rgba(239,68,68,.1);border-radius:8px;display:none}
        .error-msg.show{display:block}
        .btn .spinner{display:none;width:20px;height:20px;border:2px solid rgba(255,255,255,.3);
            border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite}
        .btn.loading .spinner{display:block}
        .btn.loading span{display:none}
        @keyframes spin{to{transform:rotate(360deg)}}
        @media(max-width:480px){.login-card{padding:28px 20px;border-radius:20px}.logo-icon{width:60px;height:60px;font-size:26px}.logo h1{font-size:24px}}
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-tasks"></i></div>
                <h1>Barmagly Tasks</h1>
                <p>نظام إدارة المهام والمشاريع</p>
            </div>
            <form id="loginForm">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> اسم المستخدم</label>
                    <input type="text" id="username" placeholder="أدخل اسم المستخدم" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> كلمة المرور</label>
                    <input type="password" id="password" placeholder="أدخل كلمة المرور" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <span>تسجيل الدخول</span>
                    <div class="spinner"></div>
                    <i class="fas fa-sign-in-alt"></i>
                </button>
                <div class="error-msg" id="errorMsg"></div>
            </form>
        </div>
    </div>
    <script>
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('loginBtn');
        const err = document.getElementById('errorMsg');
        btn.classList.add('loading');
        err.classList.remove('show');
        try {
            const res = await fetch('/login', {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'},
                body: JSON.stringify({username: document.getElementById('username').value, password: document.getElementById('password').value})
            });
            const data = await res.json();
            if (data.success) { window.location.href = '/'; }
            else { err.textContent = data.error || 'خطأ في تسجيل الدخول'; err.classList.add('show'); }
        } catch(e) { err.textContent = 'خطأ في الاتصال بالسيرفر'; err.classList.add('show'); }
        btn.classList.remove('loading');
    });
    </script>
</body>
</html>
