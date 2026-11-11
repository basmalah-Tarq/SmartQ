/* SmartQ i18n + theme (website). Loaded synchronously in <head>.
   - Language: cookie "smartq_lang" (en|ar, default en). PHP pages are translated on the server
     (lang/*.php); this file translates JS-generated strings and the static landing page
     (index.html: [data-i18n], [data-i18n-html], [data-i18n-attr="attr:key;attr:key"]).
   - Theme: localStorage + cookie "smartq_theme" (light|dark). No choice = follow the system.
   - Switches: [data-sq-lang="ar|en"] and [data-sq-theme-toggle]. */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement;

  function readCookie(name) {
    var m = doc.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }
  function writeCookie(name, value) {
    doc.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=31536000; SameSite=Lax';
  }
  function store(name, value) { try { localStorage.setItem(name, value); } catch (e) {} }

  var lang = window.SQ_LANG || readCookie('smartq_lang') || 'en';
  if (lang !== 'ar') lang = 'en';
  root.setAttribute('lang', lang);
  root.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');

  /* ---------- Arabic dictionary (JS strings + landing page) ---------- */
  var AR = {
    // shared JS strings
    'js.turn': 'حان دورك!',
    'js.ahead_one': 'شخص واحد أمامك',
    'js.ahead_many': '{n} أشخاص أمامك',
    'js.now_serving': 'يُخدم الآن {n}',
    'js.logout_title': 'تسجيل الخروج من SmartQ؟',
    'js.logout_desc': 'ستحتاج إلى تسجيل الدخول مجدداً لرؤية حجوزاتك وطابورك.',
    'js.cancel': 'إلغاء',
    'js.logout': 'تسجيل الخروج',
    'js.close': 'إغلاق',
    'js.show_password': 'إظهار كلمة المرور',
    'js.hide_password': 'إخفاء كلمة المرور',
    'js.birth_date': 'تاريخ الميلاد',
    'js.optional': '(اختياري)',
    'js.day': 'اليوم',
    'js.month': 'الشهر',
    'js.year': 'السنة',
    'js.months': 'يناير|فبراير|مارس|أبريل|مايو|يونيو|يوليو|أغسطس|سبتمبر|أكتوبر|نوفمبر|ديسمبر',
    'js.date_hint': 'اختر اليوم والشهر والسنة — أو اترك الثلاثة فارغة.',
    'js.pw_levels': 'قصيرة جداً|ضعيفة|مقبولة|جيدة|قوية',
    'js.err_generic': 'حدث خطأ ما. يرجى المحاولة مرة أخرى.',
    'js.err_network': 'خطأ في الشبكة. تحقّق من اتصالك وحاول مرة أخرى.',
    'js.resend': 'إعادة إرسال الرمز',
    'js.resend_in': 'إعادة الإرسال بعد {t}',
    'js.sending': 'جارٍ الإرسال…',
    'js.sending_code': 'جارٍ إرسال الرمز…',
    'js.new_code': 'أرسلنا لك رمزاً جديداً.',
    'js.send_failed': 'تعذّر إرسال الرمز. يرجى المحاولة مرة أخرى.',
    'js.enter_digits': 'أدخل الأرقام الستة الموجودة في البريد.',
    'js.checking': 'جارٍ التحقق من الرمز…',
    'js.signed_in': 'تم الدخول — جارٍ فتح مساحة عملك…',
    'js.code_failed': 'هذا الرمز غير صحيح. يرجى المحاولة مرة أخرى.',
    'js.google_failed': 'فشل الدخول عبر Google. يرجى المحاولة مرة أخرى.',
    'js.pw_mismatch': 'كلمتا المرور غير متطابقتين.',
    'js.choose_x': 'يرجى اختيار {x}.',
    'js.enter_x': 'يرجى إدخال {x}.',
    'js.this_field': 'هذا الحقل',
    'js.email_invalid': 'أدخل بريداً إلكترونياً صحيحاً، مثل name@example.com.',
    'js.min_chars': 'استخدم {n} أحرف على الأقل.',
    'js.check_format': 'يرجى التحقق من الصيغة.',
    'js.check_field': 'يرجى التحقق من هذا الحقل.',
    'js.select_role': 'يرجى اختيار دورك أولاً.',

    // landing (index.html)
    'land.title': 'SmartQ | إدارة الطوابير وحجز المواعيد',
    'land.home_aria': 'الصفحة الرئيسية لـ SmartQ',
    'land.tag': 'إدارة الطوابير والحجوزات',
    'land.nav_product': 'المنتج',
    'land.nav_how': 'كيف يعمل',
    'land.nav_industries': 'القطاعات',
    'land.nav_pricing': 'الأسعار',
    'land.nav_faq': 'الأسئلة الشائعة',
    'land.login': 'تسجيل الدخول',
    'land.get_started': 'ابدأ الآن',
    'land.toggle_nav': 'فتح القائمة',
    'land.eyebrow': 'إدارة الطوابير وحجز المواعيد',
    'land.h1': 'احجز دورك.<br><span class="sq-grad-text">وفّر وقتك.</span>',
    'land.lead': 'يتيح SmartQ للعملاء حجز موعد أو أخذ رقم في الطابور المباشر عبر الإنترنت — ويمنح العيادات والبنوك والصالونات والمكاتب لوحة تحكم هادئة واحدة لخدمتهم في الوقت المحدد.',
    'land.cta_free': 'ابدأ مجاناً',
    'land.m_serving': 'يُخدم الآن',
    'land.m_your_turn': 'دورك',
    'land.m_meta': '10:30 ص · عيادة المدينة',
    'land.browse': 'تصفّح الأماكن كضيف، بدون حساب',
    'land.apk': 'حمّل تطبيق أندرويد (APK)',
    'land.how_install': 'كيف أثبّته؟',
    'land.point1': 'حجز عبر الإنترنت على مدار الساعة',
    'land.point2': 'رقم «يُخدم الآن» مباشر',
    'land.point3': 'مجاني للعملاء',
    'land.visual_aria': 'معاينة SmartQ: لوحة «يُخدم الآن» مباشرة، وتذكرة دورك، والمواعيد المتاحة اليوم',
    'land.greet': 'صباح الخير يا سارة',
    'land.your_queue': 'طابورك المباشر',
    'land.live': 'مباشر',
    'land.serving_at': 'يُخدم الآن · عيادة المدينة',
    'land.counter': 'الشبّاك 2',
    'land.ahead5': '5 أشخاص أمامك',
    'land.your_number': 'رقم دورك',
    'land.eta': '10:30 ص · الوصول خلال ~12 دقيقة',
    'land.book_at': 'احجز موعداً · عيادة المدينة',
    'land.d_mon': 'الاثنين', 'land.d_tue': 'الثلاثاء', 'land.d_wed': 'الأربعاء', 'land.d_thu': 'الخميس', 'land.d_fri': 'الجمعة', 'land.d_sat': 'السبت',
    'land.next': 'التالي',
    'land.you': 'أنت',
    'land.sara': 'سارة م.',
    'land.chip_a': 'تم تأكيد الحجز',
    'land.chip_a_sub': 'عيادة المدينة · الثلاثاء 10:30 ص',
    'land.chip_b': 'متوسط الانتظار اليوم',
    'land.chip_b_sub': '9 دقائق · بعد أن كان 25',
    'land.strip_aria': 'أماكن تستخدم SmartQ',
    'land.strip': 'مصمَّم لكل مكان فيه طابور انتظار',
    'land.i_clinics': 'العيادات', 'land.i_hospitals': 'المستشفيات', 'land.i_banks': 'البنوك', 'land.i_salons': 'الصالونات',
    'land.i_beauty': 'مراكز التجميل', 'land.i_gyms': 'النوادي الرياضية', 'land.i_offices': 'الدوائر الحكومية',
    'land.one_system': 'نظام واحد',
    'land.prod_h2': 'المواعيد والطوابير المباشرة والتحليلات — في مكان واحد.',
    'land.prod_p': 'كل ما تحتاجه مكاتب الاستقبال المزدحمة لاستبدال قاعة الانتظار المكتظة بتدفق منظَّم وفي الوقت المحدد.',
    'land.tue10': 'الثلاثاء 10',
    'land.p1_h': 'حجز المواعيد',
    'land.p1_p': 'يختار العملاء اليوم والموعد المتاح عبر الإنترنت على مدار الساعة. ينشر المدير الجدول مرة واحدة ويقسّمه SmartQ إلى مواعيد قابلة للحجز.',
    'land.your_ticket': 'تذكرتك',
    'land.ahead3': '3 أمامك · خلال ~8 دقائق',
    'land.p2_h': 'طابور افتراضي وتذاكر',
    'land.p2_p': 'كل حجز يحصل على رقم في الطابور مثل <b dir="ltr">Q-046</b>. يتابع العملاء «يُخدم الآن» مباشرةً ويصلون عندما يحين دورهم تماماً.',
    'land.p3_h': 'لوحة تحكم وتحليلات',
    'land.p3_p': 'اطّلع على طابور اليوم، وأكّد الزيارات أو أنهِها، واكتشف أوقات الذروة، وصدّر الحجوزات إلى Excel أو PDF بنقرة واحدة.',
    'land.for_managers': 'للمدراء',
    'land.dash_h2': 'أدِر يومك كاملاً من شاشة واحدة.',
    'land.dash_p': 'تعرض مساحة عمل المدير من ينتظر ومن التالي وكيف يسير اليوم — بلا قوائم ورقية ولا مناداة بالأسماء.',
    'land.check1': 'أكّد الحجز أو أكمله أو سجّله كفائت',
    'land.check2': 'مواعيد تُنشأ تلقائياً من ساعات العمل',
    'land.check3': 'قائمة العملاء مع تصدير Excel وPDF',
    'land.check4': 'يشرف المسؤولون على كل مكان ومدير وحجز',
    'land.register_place': 'سجّل مكانك',
    'land.dash_aria': 'معاينة لوحة تحكم المدير',
    'land.dental': 'عيادة الابتسامة المشرقة',
    'land.todays_queue': 'طابور اليوم',
    'land.waiting': 'بالانتظار',
    'land.served': 'خُدموا اليوم',
    'land.avg_wait': 'متوسط الانتظار',
    'land.min': 'د',
    'land.no_shows': 'لم يحضروا',
    'land.up_next': 'التالي في الدور',
    'land.r1': 'سارة م. · تنظيف',
    'land.r2': 'عمر ك. · فحص',
    'land.r3': 'لينا أ. · تبييض',
    'land.r4': 'كريم س. · استشارة',
    'land.serving': 'قيد الخدمة',
    'land.confirmed': 'مؤكَّد',
    'land.peak': 'أوقات الذروة',
    'land.how_eyebrow': 'كيف يعمل',
    'land.how_h2': 'من البحث إلى دورك في أربع خطوات.',
    'land.how_p': 'يُبقي SmartQ رحلتك واضحة بالكامل — من إيجاد المكان حتى الدخول في موعدك تماماً.',
    'land.s1_h': 'ابحث عن مكان',
    'land.s1_p': 'ابحث في العيادات والبنوك والصالونات والنوادي والمكاتب بالاسم أو المدينة أو الفئة.',
    'land.s2_h': 'اختر وقتاً',
    'land.s2_p': 'اختر يوماً وموعداً متاحاً — أو دع SmartQ يقترح الأفضل.',
    'land.s3_h': 'احصل على رقمك',
    'land.s3_p': 'أكّد واحصل على تذكرة دورك فوراً، مثل <strong dir="ltr">Q-007</strong>.',
    'land.s4_h': 'احضر في الوقت',
    'land.s4_p': 'تابع الطابور المباشر من متصفحك وادخل عندما يقترب دورك.',
    'land.ind_eyebrow': 'القطاعات',
    'land.ind_h2': 'منصة واحدة لكل طابور تفضّل تجاوزه.',
    'land.ind_p': 'من زيارة الطبيب إلى قصّة شعرك القادمة — احجزها كلها بالطريقة البسيطة نفسها.',
    'land.c1': 'العيادات', 'land.c1s': 'زيارات الأطباء',
    'land.c2': 'المستشفيات', 'land.c2s': 'فحوصات وتحاليل',
    'land.c3': 'البنوك', 'land.c3s': 'خدمات الفروع',
    'land.c4': 'الصالونات', 'land.c4s': 'الشعر والتجميل',
    'land.c5': 'النوادي الرياضية', 'land.c5s': 'جلسات تدريب',
    'land.c6': 'الدوائر الحكومية', 'land.c6s': 'خدمات المواطنين',
    'land.st1': 'حجز عبر الإنترنت ليلاً ونهاراً',
    'land.st2': 'لحجز دورك',
    'land.st3': 'مساحات عمل: عميل، مدير، مسؤول',
    'land.st4': 'تطبيقات مطلوبة — يعمل في المتصفح',
    'land.why': 'لماذا يحبّه الناس',
    'land.why_h2': 'انتظار أقل. حياة أكثر.',
    'land.stars': '5 نجوم',
    'land.q1': '«حجزت زيارتي للعيادة من حاسوبي ودخلت عندما ظهر رقمي. لا مزيد من الجلوس في قاعة انتظار مزدحمة.»',
    'land.q1_who': 'مراجِعة عيادة', 'land.q1_role': 'عميلة',
    'land.q2': '«إنشاء يوم كامل من المواعيد يستغرق ثوانٍ. وقائمة الحجوزات تخبر فريقي بدقة من التالي.»',
    'land.q2_who': 'مديرة صالون', 'land.q2_role': 'مديرة',
    'land.q3': '«تعرض لوحة المسؤول أكثر الأماكن ازدحاماً وأوقات الذروة بنظرة واحدة، وتُصدَّر التقارير بنقرة.»',
    'land.q3_who': 'مسؤول المنصّة', 'land.q3_role': 'مسؤول',
    'land.pricing': 'الأسعار',
    'land.pr_h2': 'باقات لكل مكان.',
    'land.pr_p': 'الحجز مجاني دائماً للعملاء. اختر الباقة التي تناسب منشأتك.',
    'land.free': 'مجانية',
    'land.first_months': '/ الأشهر الأولى',
    'land.free_p': 'جرّب SmartQ وابدأ بتنظيم طابورك.',
    'land.f1': 'حجوزات عبر الإنترنت', 'land.f2': 'أرقام الطابور', 'land.f3': 'مكان واحد',
    'land.start_free': 'ابدأ مجاناً',
    'land.popular': 'الأكثر طلباً',
    'land.plus': 'بلس',
    'land.month': '/ شهرياً',
    'land.plus_p': 'للفرق الصغيرة التي تحتاج أدوات الحجز والطوابير.',
    'land.pl1': 'كل ما في الباقة المجانية', 'land.pl2': 'مواعيد تُنشأ تلقائياً', 'land.pl3': 'قائمة العملاء والتصدير',
    'land.choose_plus': 'اختر بلس',
    'land.pro': 'برو',
    'land.pro_p': 'أدوات متقدمة للأماكن المزدحمة وإدارة كاملة.',
    'land.pp1': 'كل ما في بلس', 'land.pp2': 'تقارير PDF', 'land.pp3': 'دعم ذو أولوية',
    'land.choose_pro': 'اختر برو',
    'land.faq': 'الأسئلة الشائعة',
    'land.faq_h2': 'أسئلة وأجوبة.',
    'land.fq1': 'هل SmartQ مجاني للعملاء؟',
    'land.fa1': 'نعم. إنشاء الحساب وحجز الدور ومتابعة طابورك مجاني تماماً للعملاء.',
    'land.fq2': 'هل أحتاج إلى كلمة مرور؟',
    'land.fa2': 'لا. أدخل بريدك الإلكتروني وسنرسل لك رمزاً من 6 أرقام لتسجيل الدخول. ويمكنك استخدام كلمة مرور إن فضّلت.',
    'land.fq3': 'هل أحتاج إلى تثبيت أي شيء؟',
    'land.fa3': 'لا. يعمل SmartQ في متصفحك على أي حاسوب أو جهاز لوحي أو هاتف. ويتوفر تطبيق أندرويد اختياري إن فضّلته.',
    'land.fq4': 'كيف أحصل على رقمي في الطابور؟',
    'land.fa4': 'اختر مكاناً، ثم جدولاً وموعداً متاحاً، ثم أكّد. تظهر تذكرتك برقم مثل <span dir="ltr">Q-007</span> فوراً وتبقى في «مواعيدي».',
    'land.fq5': 'أدير عيادة / صالوناً. كيف أبدأ؟',
    'land.fa5': 'اختر «سجّل منشأتك» في صفحة إنشاء الحساب، وأضف بيانات مكانك، ثم أنشئ جدولاً — وسيقسّمه SmartQ تلقائياً إلى مواعيد قابلة للحجز.',
    'land.and_h': 'تفضّل الهاتف؟ يتوفر تطبيق أندرويد أيضاً.',
    'land.and_p': 'نفس الحساب والحجوزات الموجودة على الموقع — اختياري تماماً.',
    'land.download_apk': 'تحميل APK',
    'land.cta_h2': 'جاهز لتتجاوز الطابور؟',
    'land.cta_p': 'أنشئ حسابك المجاني واحجز دورك الأول في أقل من دقيقة.',
    'land.foot_tag': 'حجز وحلول ذكية',
    'land.foot_p': 'إدارة الطوابير وحجز المواعيد بوضوح أكبر للجميع — العملاء والمدراء والمسؤولين.',
    'land.foot_product': 'المنتج',
    'land.features': 'المزايا',
    'land.android_opt': 'تطبيق أندرويد (اختياري)',
    'land.account': 'الحساب',
    'land.create_account': 'إنشاء حساب',
    'land.contact': 'تواصل معنا',
    'land.email_us': 'راسلنا',
    'land.copyright': '© SmartQ — احجز دورك. وفّر وقتك.',
    'land.top': 'العودة للأعلى',
    'land.close': 'إغلاق',
    'land.inst_h': 'ثبّت تطبيق SmartQ لأندرويد',
    'land.inst_p': 'التطبيق اختياري — الموقع يقوم بكل شيء. التثبيت يستغرق دقيقة تقريباً.',
    'land.inst1': '<strong>حمّل ملف APK</strong>اضغط «حمّل تطبيق أندرويد (APK)» من هاتفك. اسم الملف <code dir="ltr">SmartQ.apk</code>.',
    'land.inst2': '<strong>اسمح «بتثبيت التطبيقات غير المعروفة»</strong>عندما يطلب أندرويد ذلك، افتح الإعدادات واسمح لمتصفحك (مثل Chrome) بتثبيت التطبيقات غير المعروفة. تفعل ذلك مرة واحدة فقط.',
    'land.inst3': '<strong>افتح وثبّت</strong>افتح الملف المحمَّل من الإشعار أو من مجلد التنزيلات واضغط <b>تثبيت</b>.',
    'land.inst4': '<strong>سجّل الدخول</strong>افتح SmartQ وسجّل الدخول ببريدك (سنرسل لك رمزاً)، أو تصفّح كضيف.',
    'land.inst_note': 'قد يحذّرك أندرويد بأن التطبيق ليس من متجر Play — هذا متوقَّع عند التحميل المباشر.'
  };

  function fill(s, vars) {
    if (!vars) return s;
    return s.replace(/\{(\w+)\}/g, function (m, k) { return vars[k] != null ? vars[k] : m; });
  }
  /** SQ_t(key, englishFallback, vars) */
  function t(key, fallback, vars) {
    var s = lang === 'ar' && AR[key] != null ? AR[key] : (fallback != null ? fallback : key);
    return fill(s, vars);
  }
  /** translate a known English server message */
  function tm(msg) {
    if (lang === 'en' || !msg) return msg;
    var map = window.SQ_MSG || {};
    return map[msg] || msg;
  }
  function ahead(left) {
    if (left === 0) return t('js.turn', "It's your turn!");
    return left === 1 ? t('js.ahead_one', '1 person ahead') : t('js.ahead_many', '{n} people ahead', { n: left });
  }

  /* ---------- static page translation ---------- */
  function apply(scope) {
    if (lang !== 'ar') return;
    scope = scope || doc;
    var i, els, el, k;
    els = scope.querySelectorAll('[data-i18n]');
    for (i = 0; i < els.length; i++) { el = els[i]; k = el.getAttribute('data-i18n'); if (AR[k] != null) el.textContent = AR[k]; }
    els = scope.querySelectorAll('[data-i18n-html]');
    for (i = 0; i < els.length; i++) { el = els[i]; k = el.getAttribute('data-i18n-html'); if (AR[k] != null) el.innerHTML = AR[k]; }
    els = scope.querySelectorAll('[data-i18n-attr]');
    for (i = 0; i < els.length; i++) {
      el = els[i];
      el.getAttribute('data-i18n-attr').split(';').forEach(function (pair) {
        var p = pair.split(':');
        if (p.length === 2 && AR[p[1].trim()] != null) el.setAttribute(p[0].trim(), AR[p[1].trim()]);
      });
    }
    var tt = doc.querySelector('title[data-i18n]');
    if (tt && AR[tt.getAttribute('data-i18n')]) doc.title = AR[tt.getAttribute('data-i18n')];
  }
  function ready() { root.classList.remove('sq-i18n-wait'); }
  window.sqI18nApply = function () { try { apply(); } finally { ready(); } };
  if (root.classList.contains('sq-i18n-wait')) setTimeout(ready, 1500); // fail-safe

  /* ---------- theme ---------- */
  function currentTheme() { return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'; }
  function setTheme(theme, persist) {
    root.setAttribute('data-theme', theme);
    if (persist) { root.removeAttribute('data-theme-auto'); store('smartq_theme', theme); writeCookie('smartq_theme', theme); }
    var meta = doc.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', theme === 'dark' ? '#0A1222' : '#F5F8FD');
    try { doc.dispatchEvent(new CustomEvent('sq:theme', { detail: theme })); } catch (e) {}
  }
  if (window.matchMedia) {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var onChange = function (e) { if (root.hasAttribute('data-theme-auto')) setTheme(e.matches ? 'dark' : 'light', false); };
    if (mq.addEventListener) mq.addEventListener('change', onChange); else if (mq.addListener) mq.addListener(onChange);
  }

  /* ---------- switches ---------- */
  doc.addEventListener('click', function (e) {
    var tgt = e.target;
    var themeBtn = tgt.closest && tgt.closest('[data-sq-theme-toggle]');
    if (themeBtn) {
      e.preventDefault();
      root.classList.add('sq-theme-anim');
      setTheme(currentTheme() === 'dark' ? 'light' : 'dark', true);
      setTimeout(function () { root.classList.remove('sq-theme-anim'); }, 400);
      return;
    }
    var langBtn = tgt.closest && tgt.closest('[data-sq-lang]');
    if (langBtn) {
      e.preventDefault();
      e.stopPropagation();
      var next = langBtn.getAttribute('data-sq-lang') === 'ar' ? 'ar' : 'en';
      writeCookie('smartq_lang', next);
      store('smartq_lang', next);
      if (next === lang) return;
      // same page, same query (minus ?lang), GET reload (never re-posts a form)
      var url = location.pathname + location.search.replace(/([?&])lang=(en|ar)(&|$)/, function (m, a, b, c) { return c ? a : ''; }) + location.hash;
      location.replace(url);
    }
  }, true);

  window.SQ_I18N = { lang: lang, t: t, tm: tm, ahead: ahead, apply: apply, setTheme: setTheme, theme: currentTheme };
})();
