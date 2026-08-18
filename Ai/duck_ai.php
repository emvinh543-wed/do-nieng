<!-- 3D DUCK AI ASSISTANT MASCOT FOR GLOWDRINKS -->
<style>
/* Duck AI Floating Widget Container - Moves horizontally */
.duck-ai-widget {
    position: fixed;
    bottom: 20px;
    right: 25px;
    z-index: 99999;
    font-family: inherit;
    transition: right 0.1s linear, left 0.1s linear;
}

/* 3D Duck Container & Ground Shadow */
.duck-3d-wrapper {
    position: relative;
    cursor: pointer;
    display: inline-block;
}

.duck-ground-shadow {
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    width: 65px;
    height: 14px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 50%;
    filter: blur(4px);
    animation: shadowWaddle 0.5s ease-in-out infinite alternate;
}

/* 3D Duck Image & Waddling Animation */
.duck-avatar-btn {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    background: radial-gradient(circle at 30% 30%, #ffffff, #fff3c4);
    border: 3.5px solid #f5a623;
    box-shadow: 0 10px 28px rgba(245, 166, 35, 0.5), inset 0 -4px 8px rgba(0,0,0,0.08);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
    animation: duckWaddleStep 0.5s ease-in-out infinite alternate;
    position: relative;
    padding: 0;
    overflow: visible;
}

.duck-avatar-btn:hover {
    transform: scale(1.18) translateY(-6px) !important;
    box-shadow: 0 16px 38px rgba(245, 166, 35, 0.75);
}

.duck-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    filter: drop-shadow(0 4px 6px rgba(0,0,0,0.15));
    transition: transform 0.2s ease;
}

/* Waving Arm Badge 🖐️ */
.duck-waving-wing {
    position: absolute;
    top: -6px;
    left: -8px;
    font-size: 1.9rem;
    animation: wingWave 0.8s ease-in-out infinite alternate;
    transform-origin: bottom right;
    pointer-events: none;
    filter: drop-shadow(0 3px 5px rgba(0,0,0,0.3));
    z-index: 10;
}

.duck-online-dot {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 18px;
    height: 18px;
    background: #22c55e;
    border: 2.5px solid white;
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
    z-index: 10;
}

/* Speech Bubble Following 3D Duck */
.duck-speech-bubble {
    position: absolute;
    bottom: 100px;
    right: 0;
    width: 270px;
    background: white;
    padding: 14px 18px;
    border-radius: 20px;
    border-bottom-right-radius: 4px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.18);
    border: 2.5px solid #f5a623;
    font-size: 0.9rem;
    color: #2d3748;
    line-height: 1.45;
    animation: bubblePop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    cursor: pointer;
    transition: transform 0.2s ease;
    z-index: 99;
}

.duck-speech-bubble:hover {
    transform: translateY(-3px) scale(1.02);
}

.duck-speech-bubble strong {
    color: #d97706;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    font-size: 0.95rem;
}

.duck-speech-bubble::after {
    content: '';
    position: absolute;
    bottom: -11px;
    right: 32px;
    border-width: 11px 11px 0 0;
    border-style: solid;
    border-color: white transparent;
    display: block;
    width: 0;
}

/* Chat Box Window */
.duck-chat-window {
    display: none;
    position: fixed;
    bottom: 115px;
    right: 25px;
    width: 370px;
    max-width: calc(100vw - 40px);
    height: 500px;
    background: white;
    border-radius: 24px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
    border: 2.5px solid #f5a623;
    overflow: hidden;
    flex-direction: column;
    z-index: 100000;
    animation: chatOpen 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.duck-chat-header {
    background: linear-gradient(135deg, #f5a623, #f7b731);
    color: white;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 3px 12px rgba(245, 166, 35, 0.3);
}

.duck-chat-header-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.duck-chat-header-img {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 2px solid white;
    object-fit: cover;
    background: white;
    animation: headerDuckJump 1.8s infinite ease-in-out;
}

.duck-chat-header-text h4 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: white;
}

.duck-chat-header-text span {
    font-size: 0.78rem;
    opacity: 0.95;
    display: flex;
    align-items: center;
    gap: 4px;
}

.duck-chat-close {
    background: rgba(255, 255, 255, 0.25);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    font-size: 1.2rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.duck-chat-close:hover {
    background: rgba(255, 255, 255, 0.4);
}

/* Chat Body */
.duck-chat-body {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    background: #fffdf7;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* Chat Messages */
.duck-msg {
    display: flex;
    gap: 10px;
    max-width: 88%;
}

.duck-msg.ai {
    align-self: flex-start;
}

.duck-msg.user {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.duck-msg-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 1.5px solid #f5a623;
    background: white;
}

.duck-msg-bubble {
    padding: 11px 15px;
    border-radius: 18px;
    font-size: 0.88rem;
    line-height: 1.5;
}

.duck-msg.ai .duck-msg-bubble {
    background: white;
    color: #2d3748;
    border: 1.5px solid #fee4b3;
    border-top-left-radius: 4px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
}

.duck-msg.user .duck-msg-bubble {
    background: linear-gradient(135deg, #f5a623, #d97706);
    color: white;
    border-top-right-radius: 4px;
    font-weight: 600;
    box-shadow: 0 3px 10px rgba(245, 166, 35, 0.3);
}

/* Quick Suggestion Chips */
.duck-quick-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 6px;
}

.duck-chip {
    background: #fff3c4;
    color: #b45309;
    border: 1.5px solid #fde047;
    padding: 7px 13px;
    border-radius: 16px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.duck-chip:hover {
    background: #f5a623;
    color: white;
    border-color: #f5a623;
    transform: translateY(-2px);
}

/* Chat Footer Input */
.duck-chat-footer {
    padding: 12px 14px;
    background: white;
    border-top: 1px solid #fee4b3;
    display: flex;
    gap: 8px;
}

.duck-chat-input {
    flex: 1;
    border: 1.5px solid #fed7aa;
    border-radius: 22px;
    padding: 9px 16px;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.2s;
}

.duck-chat-input:focus {
    border-color: #f5a623;
}

.duck-chat-send {
    background: #f5a623;
    color: white;
    border: none;
    border-radius: 50%;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.1rem;
    transition: background 0.2s, transform 0.2s;
}

.duck-chat-send:hover {
    background: #d97706;
    transform: scale(1.08);
}

/* 3D WALKING & WADDLING KEYFRAME ANIMATIONS */
@keyframes duckWaddleStep {
    0% { transform: translateY(0) rotate(-6deg); }
    100% { transform: translateY(-7px) rotate(6deg); }
}

@keyframes shadowWaddle {
    0% { transform: translateX(-50%) scale(1); opacity: 0.25; }
    100% { transform: translateX(-50%) scale(0.75); opacity: 0.12; }
}

@keyframes wingWave {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(28deg); }
}

@keyframes headerDuckJump {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

@keyframes bubblePop {
    0% { transform: scale(0.5) translateY(15px); opacity: 0; }
    100% { transform: scale(1) translateY(0); opacity: 1; }
}

@keyframes chatOpen {
    0% { transform: scale(0.7) translateY(40px); opacity: 0; }
    100% { transform: scale(1) translateY(0); opacity: 1; }
}
</style>

<!-- WIDGET HTML -->
<div class="duck-ai-widget" id="duck-ai-widget">
    <!-- Speech Bubble Following 3D Duck -->
    <div class="duck-speech-bubble" id="duck-speech-bubble" onclick="toggleDuckChat()">
        <strong data-i18n="duck_bubble_title">🦆 Vịt AI đang đi dạo! 👋</strong>
        <span id="duck-bubble-text" data-i18n="duck_bubble_text">Chào! Tớ là trợ lý Vịt AI. Tớ đi quanh trang để giúp bạn — hỏi mình bất kỳ điều gì nhé!</span>
    </div>

    <!-- 3D Duck Wrapper with Ground Shadow -->
    <div class="duck-3d-wrapper" onclick="toggleDuckChat()">
        <div class="duck-ground-shadow"></div>
        <button class="duck-avatar-btn" id="duck-avatar-btn" title="Trợ Lý Vịt AI">
            <span class="duck-waving-wing">👋</span>
            <!-- Lottie animation container (preferred) + fallback img -->
            <div id="anime-lottie" style="width:100%;height:100%;border-radius:50%;overflow:hidden;"></div>
            <img id="anime-fallback" src="/anh/duck_3d.png" alt="Duck AI Mascot" class="duck-avatar-img" style="display:none;" onerror="this.style.display='none'">
            <span class="duck-online-dot"></span>
        </button>
    </div>

    <!-- Chat Box Window -->
    <div class="duck-chat-window" id="duck-chat-window">
        <!-- Header -->
        <div class="duck-chat-header">
            <div class="duck-chat-header-info">
                <div style="width:44px;height:44px;border-radius:50%;overflow:hidden;">
                    <div id="anime-header-lottie" style="width:44px;height:44px"></div>
                    <img id="anime-header-fallback" src="/anh/duck_3d.png" alt="Duck AI" class="duck-chat-header-img" style="display:none;" onerror="this.style.display='none'">
                </div>
                <div class="duck-chat-header-text">
                    <h4 data-i18n="duck_header_title">Vịt AI</h4>
                    <span data-i18n="duck_header_status">🟢 Trợ lý ảo GlowDrinks (Online)</span>
                </div>
            </div>
            <button class="duck-chat-close" onclick="toggleDuckChat()">✕</button>
        </div>

        <!-- Chat Messages -->
        <div class="duck-chat-body" id="duck-chat-body">
            <!-- Welcome Message -->
            <div class="duck-msg ai">
                <div style="width:35px;height:35px;border-radius:50%;overflow:hidden;">
                    <div id="anime-msg-lottie" style="width:35px;height:35px"></div>
                    <img id="anime-msg-fallback" src="/anh/duck_3d.png" class="duck-msg-avatar" style="display:none;" onerror="this.style.display='none'">
                </div>
                <div class="duck-msg-bubble" data-i18n="duck_welcome_html">
                    Xin chào! 👋 Chào mừng bạn đến với <strong>GlowDrinks</strong>!<br><br>
                    Tớ là trợ lý <strong>Vịt AI</strong>, có thể giúp bạn tìm món, đặt hàng hoặc hướng dẫn thanh toán.<br><br>
                    <strong>Bạn cần hỗ trợ gì hôm nay?</strong>
                </div>
            </div>

            <!-- Quick Suggestions -->
            <div class="duck-quick-chips" id="duck-quick-chips">
                <a href="/Game/duck_game.php" target="_blank" class="duck-chip" style="background:#f5a623;color:white;border-color:#f5a623;text-decoration:none;" data-i18n="duck_chip_play">🎮 Chơi Game Vịt 3D (Nhận Mã Giảm Giá)</a>
                <button class="duck-chip" onclick="askDuck('Quán có những món nước nào ngon?')" data-i18n="duck_chip_best">🥤 Món nước bán chạy?</button>
                <button class="duck-chip" onclick="askDuck('Thời gian giao hàng bao lâu?')" data-i18n="duck_chip_delivery">🛵 Giao hàng bao lâu?</button>
                <button class="duck-chip" onclick="askDuck('Thanh toán bằng mã QR như thế nào?')" data-i18n="duck_chip_qr">📱 Thanh toán QR?</button>
                <button class="duck-chip" onclick="askDuck('Làm sao để đặt hàng?')" data-i18n="duck_chip_order">🛒 Hướng dẫn đặt hàng</button>
            </div>
        </div>

        <!-- Footer Input -->
        <div class="duck-chat-footer">
            <input type="text" id="duck-chat-input" class="duck-chat-input" data-i18n-placeholder="duck_input_placeholder" placeholder="" onkeypress="handleDuckKeyPress(event)">
            <button class="duck-chat-send" onclick="sendDuckMessage()">➔</button>
        </div>
    </div>
</div>

<!-- 3D DUCK WALKING & INTERACTION LOGIC -->
<script>
// Web Audio API Synthesized Quack Sound
function playDuckQuackSound() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(480, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(200, ctx.currentTime + 0.16);

        gain.gain.setValueAtTime(0.35, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.16);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start();
        osc.stop(ctx.currentTime + 0.16);
    } catch (e) {
        console.log('Audio error:', e);
    }
}

// WALKING SYSTEM VARIABLES
let duckPos = 25; // current position from right in px
let duckDir = -1; // -1: walking left, 1: walking right
let isDuckWalking = true;
let duckWalkInterval = null;

function startDuckWalking() {
    if (duckWalkInterval) clearInterval(duckWalkInterval);

    const widget = document.getElementById('duck-ai-widget');
    const imgEl  = document.getElementById('duck-img-el');
    const bubble = document.getElementById('duck-speech-bubble');
    if (!widget) return;

    const maxWalk = Math.min(window.innerWidth - 120, 600); // Walk range up to 600px

    duckWalkInterval = setInterval(function() {
        if (!isDuckWalking) return;

        // Advance position
        duckPos += (duckDir * 2.5); // speed

        // Reach left boundary -> Turn around to right
        if (duckPos >= maxWalk) {
            duckPos = maxWalk;
            duckDir = -1; // turn rightward
            if (imgEl) imgEl.style.transform = 'scaleX(1)';
            if (bubble) bubble.style.right = '0';
        }
        // Reach right boundary -> Turn around to left
        else if (duckPos <= 25) {
            duckPos = 25;
            duckDir = 1; // turn leftward
            if (imgEl) imgEl.style.transform = 'scaleX(-1)';
            if (bubble) bubble.style.right = '0';
        }

        widget.style.right = duckPos + 'px';
    }, 50);
}

function toggleDuckChat() {
    const win = document.getElementById('duck-chat-window');
    const bubble = document.getElementById('duck-speech-bubble');
    const btn = document.getElementById('duck-avatar-btn');
    if (!win) return;
    
    playDuckQuackSound();

    if (win.style.display === 'flex') {
        win.style.display = 'none';
        if (bubble) bubble.style.display = 'block';
        isDuckWalking = true; // Resume walking
    } else {
        win.style.display = 'flex';
        if (bubble) bubble.style.display = 'none';
        isDuckWalking = false; // Stop walking while chatting
    }
}

function handleDuckKeyPress(e) {
    if (e.key === 'Enter') {
        sendDuckMessage();
    }
}

function askDuck(question) {
    const input = document.getElementById('duck-chat-input');
    if (input) {
        input.value = question;
        sendDuckMessage();
    }
}

function sendDuckMessage() {
    const input = document.getElementById('duck-chat-input');
    const text = input ? input.value.trim() : '';
    if (!text) return;

    playDuckQuackSound();

    appendDuckMsg(text, 'user');
    input.value = '';
    scrollDuckChatBottom();

    setTimeout(function() {
        playDuckQuackSound();
        const reply = generateDuckReply(text);
        appendDuckMsg(reply, 'ai');
        scrollDuckChatBottom();
    }, 550);
}

function appendDuckMsg(text, sender) {
    const body = document.getElementById('duck-chat-body');
    if (!body) return;

    const div = document.createElement('div');
    div.className = 'duck-msg ' + sender;

    if (sender === 'ai') {
        div.innerHTML = `
            <img src="/anh/duck_3d.png" class="duck-msg-avatar" onerror="this.src='/anh/duck_ai.png'">
            <div class="duck-msg-bubble">${text}</div>
        `;
    } else {
        div.innerHTML = `
            <div class="duck-msg-bubble">${text}</div>
        `;
    }

    body.appendChild(div);
}

function generateDuckReply(question) {
    const q = question.toLowerCase();

    if (q.includes('món') || q.includes('nước') || q.includes('menu') || q.includes('bán chạy') || q.includes('ngon')) {
        return "Quạc quạc! 🐥 Các món 'Bestseller' đỉnh nhất tại GlowDrinks gồm:<br>• <strong>Trà Sữa Trân Châu Hoàng Gia</strong> 🧋<br>• <strong>Cà Phê Muối Đậm Đà</strong> ☕<br>• <strong>Trà Đào Cam Sả Tươi Mát</strong> 🍹<br>• <strong>Nước Ép Dưa Hấu Nguyên Chất</strong> 🍉<br><br>Bạn hãy vào mục <strong>Menu / Thức Đơn</strong> để chọn ngay ly nước yêu thích nhé!";
    }

    if (q.includes('giao') || q.includes('ship') || q.includes('bao lâu') || q.includes('thời gian')) {
        return "Quạc quạc! 🛵 GlowDrinks cam kết giao hàng siêu tốc trong vòng <strong>20 - 30 phút</strong>!<br>Trên trang theo dõi đơn hàng còn có <strong>Đồng hồ đếm ngược</strong> và <strong>Bản đồ Shipper trực tiếp</strong> để bạn xem shipper đang chạy tới đâu nữa đó!";
    }

    if (q.includes('qr') || q.includes('thanh toán') || q.includes('ngân hàng') || q.includes('tiền')) {
        return "Quạc quạc! 💳 Quán tớ hỗ trợ:<br>1️⃣ <strong>Thanh toán Mã QR Ngân hàng (VietQR)</strong> – Quét QR chuyển khoản tự động cực nhanh.<br>2️⃣ <strong>Thanh toán tiền mặt (COD)</strong> khi nhận hàng.<br>3️⃣ <strong>Ví điện tử MoMo</strong>!";
    }

    if (q.includes('đặt') || q.includes('mua') || q.includes('hướng dẫn')) {
        return "Quạc quạc! 🛒 Để đặt hàng bạn chỉ cần:<br>1️⃣ Bấm <strong>'Thêm vào giỏ'</strong> món bạn thích.<br>2️⃣ Đăng nhập tài khoản.<br>3️⃣ Điền địa chỉ nhận hàng và chọn thanh toán QR hoặc COD.<br>4️⃣ Bấm <strong>Xác nhận đặt hàng</strong> là xong ngay!";
    }

    if (q.includes('chào') || q.includes('hi') || q.includes('hello')) {
        return "Quạc quạc! 👋 🦆 Chào bạn nha! Vịt AI rất vui được làm quen với bạn. Bạn cần Vịt tư vấn món nước hay hỗ trợ đặt hàng gì không nè?";
    }

    return "Quạc quạc! 🦆 Vịt AI đã ghi nhận câu hỏi của bạn. GlowDrinks luôn sẵn sàng phục vụ bạn những ly đồ uống thơm ngon nhất! Bạn có muốn Vịt tư vấn thêm về <strong>Menu món uống</strong> hay <strong>Phương thức thanh toán QR</strong> không?";
}

function scrollDuckChatBottom() {
    const body = document.getElementById('duck-chat-body');
    if (body) {
        body.scrollTop = body.scrollHeight;
    }
}

// Start 3D Duck walking on page load after 1 second
window.addEventListener('DOMContentLoaded', function() {
    setTimeout(startDuckWalking, 1000);
    // Load Lottie animations for the anime avatar (if available)
    setTimeout(loadLottieAnimations, 400);
});

// Load lottie-web and initialize containers; fallback to static image if unavailable.
function loadLottieAnimations() {
    const lottieCdn = 'https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.10.2/lottie.min.js';
    function showFallback(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'block';
    }

    // JSON animation source - replace with your character JSON or local path
    const defaultJson = '/anh/anime_character.json'; // place your lottie json here
    const sampleJson = 'https://assets2.lottiefiles.com/packages/lf20_jtbfg2nb.json';

    function initLottie() {
        try {
            if (!window.lottie) return; // safety

            const opts = [
                {id: 'anime-lottie', path: defaultJson},
                {id: 'anime-header-lottie', path: defaultJson},
                {id: 'anime-msg-lottie', path: defaultJson}
            ];

            opts.forEach(o => {
                const container = document.getElementById(o.id);
                if (!container) return;
                // try local json first, then sample remote json
                fetch(o.path, {method: 'HEAD'}).then(r => {
                    const url = (r.ok) ? o.path : sampleJson;
                    window.lottie.loadAnimation({
                        container: container,
                        renderer: 'svg',
                        loop: true,
                        autoplay: true,
                        path: url
                    });
                }).catch(() => {
                    // couldn't fetch head -> try sample
                    window.lottie.loadAnimation({
                        container: container,
                        renderer: 'svg',
                        loop: true,
                        autoplay: true,
                        path: sampleJson
                    });
                });
            });
        } catch (e) {
            // show fallback images
            showFallback('anime-fallback');
            showFallback('anime-header-fallback');
            showFallback('anime-msg-fallback');
            console.log('Lottie init error', e);
        }
    }

    // Dynamically load lottie script then init
    if (window.lottie) {
        initLottie();
        return;
    }

    const s = document.createElement('script');
    s.src = lottieCdn;
    s.onload = initLottie;
    s.onerror = function() {
        showFallback('anime-fallback');
        showFallback('anime-header-fallback');
        showFallback('anime-msg-fallback');
    };
    document.head.appendChild(s);
}

// Sprite-sheet canvas avatar loader (uses /anh/ai_sprite.png if present)
function loadSpriteAvatar() {
    const spritePath = '/anh/ai_sprite.png';
    // Default sprite config - adjust if your sheet differs
    const spriteConfig = {
        path: spritePath,
        frameWidth: 48,
        frameHeight: 64,
        cols: 6,
        rows: 4,
        frameCount: 24,
        fps: 10
    };

    // Check if sprite exists via HEAD
    fetch(spritePath, { method: 'HEAD' }).then(r => {
        if (!r.ok) throw new Error('no-sprite');
        // create canvas inside anime-lottie container
        const container = document.getElementById('anime-lottie');
        if (!container) return;
        container.innerHTML = '';
        const canvas = document.createElement('canvas');
        canvas.width = spriteConfig.frameWidth;
        canvas.height = spriteConfig.frameHeight;
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.id = 'anime-sprite-canvas';
        container.appendChild(canvas);

        const ctx = canvas.getContext('2d');
        const img = new Image();
        img.src = spriteConfig.path;

        let frame = 0;
        const interval = 1000 / spriteConfig.fps;
        let last = performance.now();

        function draw(now) {
            const dt = now - last;
            if (dt >= interval) {
                last = now - (dt % interval);
                const fx = (frame % spriteConfig.cols) * spriteConfig.frameWidth;
                const fy = Math.floor(frame / spriteConfig.cols) * spriteConfig.frameHeight;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.imageSmoothingEnabled = false; // keep pixel art crisp
                ctx.drawImage(img, fx, fy, spriteConfig.frameWidth, spriteConfig.frameHeight, 0, 0, canvas.width, canvas.height);
                frame = (frame + 1) % spriteConfig.frameCount;
            }
            requestAnimationFrame(draw);
        }

        img.onload = function() {
            requestAnimationFrame(draw);
            // hide fallback images if any
            const fb = document.getElementById('anime-fallback'); if (fb) fb.style.display = 'none';
            const hfb = document.getElementById('anime-header-fallback'); if (hfb) hfb.style.display = 'none';
            const mfb = document.getElementById('anime-msg-fallback'); if (mfb) mfb.style.display = 'none';
        };

        img.onerror = function() {
            console.log('Sprite load error, will fallback to Lottie/image');
        };
    }).catch(() => {
        // sprite not found, do nothing here
        console.log('No sprite sheet found at', spritePath);
    });
}

// Try sprite avatar first, then Lottie
function initAvatar() {
    loadSpriteAvatar();
    // still load lottie as secondary option (it will only show if sprite not present)
    loadLottieAnimations();
}
</script>
