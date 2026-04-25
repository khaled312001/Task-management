# طرق نشر الموقع

## الخيار 1: GitHub Actions → Hostinger (الأنسب لـ Laravel) ✅

كل ما تعمل push على main، الموقع هيتحدث تلقائياً على tasks.barmagly.tech

### إعداد GitHub Secrets (مرة واحدة فقط):

1. ادخل على repo > Settings > Secrets and variables > Actions
2. أضف الـ secrets دي:

| Secret Name | القيمة |
|-------------|--------|
| `SSH_HOST` | `82.198.227.175` |
| `SSH_USER` | `u492425110` |
| `DEPLOY_PATH` | `/home/u492425110/domains/barmagly.tech/public_html/tasks` |
| `SSH_PRIVATE_KEY` | محتوى ملف `id_rsa` الخاص (انظر تحت) |

### إنشاء SSH Key للـ Deployment:

شغّل دول على جهازك:

```bash
# توليد مفتاح جديد
ssh-keygen -t ed25519 -f ~/.ssh/hostinger_deploy -N ""

# انسخ المفتاح العام للسيرفر
ssh-copy-id -i ~/.ssh/hostinger_deploy.pub -p 65002 u492425110@82.198.227.175

# اعرض المفتاح الخاص واعمله copy واحطه في GitHub Secret SSH_PRIVATE_KEY
cat ~/.ssh/hostinger_deploy
```

### بعد الإعداد:

```bash
git add .
git commit -m "your changes"
git push origin main
```

هتلاقي الـ deploy بيشتغل تلقائي في tab "Actions" على GitHub، وبعد ~2 دقيقة الموقع متحدث.

---

## الخيار 2: Vercel ❌ (لا يُنصح به)

**Vercel مش مناسب لـ Laravel** لأن:
- مفيش PHP runtime افتراضي
- Serverless = مفيش sessions/cache مستمرة
- مفيش uploads persistent
- مفيش database (لازم Hostinger للـ MySQL)

لو لازم تستخدم Vercel، الـ `vercel.json` الحالي هيخليه يعمل build بس مش هيشتغل PHP. الموقع هيظهر static فقط.

---

## النشر اليدوي (للطوارئ):

```bash
ssh -p 65002 u492425110@82.198.227.175
cd domains/barmagly.tech/public_html/tasks
git pull origin main
php artisan migrate --force
php artisan config:cache
php artisan view:cache
php artisan route:cache
```
