// parcer.cjs
const path = require('path');
const fs = require('fs');

try {
    const puppeteerExtraPath = path.join(__dirname, 'node_modules', 'puppeteer-extra');
    const stealthPath = path.join(__dirname, 'node_modules', 'puppeteer-extra-plugin-stealth');
    const puppeteer = require(puppeteerExtraPath);
    puppeteer.use(require(stealthPath)());
    global.puppeteerApp = puppeteer;
} catch (e) {
    global.puppeteerApp = require(path.join(__dirname, 'node_modules', 'puppeteer'));
}

const urlFilePath = path.resolve(__dirname, 'url.txt');
if (!fs.existsSync(urlFilePath)) {
    console.log(JSON.stringify({ error: "Файл url.txt не найден сервером Laravel." }));
    process.exit(1);
}

let originalUrl = fs.readFileSync(urlFilePath, 'utf-8');

(async () => {
    let browser;
    try {
        originalUrl = originalUrl.trim().replace(/['"]+/g, '');
        if (originalUrl.includes('?')) {
            originalUrl = originalUrl.split('?')[0];
        }
        if (!originalUrl.endsWith('/')) originalUrl += '/';
        if (!originalUrl.endsWith('reviews/')) originalUrl += 'reviews/';

        const userDataDir = path.join(__dirname, 'yandex_user_data');

        browser = await global.puppeteerApp.launch({
            headless: false,
            userDataDir: userDataDir,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-web-security',
                '--lang=ru-RU,ru',
                '--disable-features=IsolateOrigins,site-per-process',
                '--disable-blink-features=AutomationControlled'
            ]
        });

        const pages = await browser.pages();
        const page = pages.length > 0 ? pages[0] : await browser.newPage();

        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
        await page.setViewport({ width: 1366, height: 768 });

        await page.goto(originalUrl, { waitUntil: 'networkidle2', timeout: 60000 });

        // ВАЖНО: Даем тебе 10 секунд! Если выскочит капча — успей кликнуть галочку "Я не робот" в окне браузера!
        console.log('Пауза 10 секунд для ручной проверки капчи в окне...');
        await new Promise(resolve => setTimeout(resolve, 10000));

        await page.mouse.move(250, 400);
        await page.mouse.click(250, 400);

        // Плавный скроллинг на 12 шагов
        for (let i = 0; i < 12; i++) {
            await page.mouse.wheel({ deltaY: 850 });
            await page.keyboard.press('PageDown');
            await new Promise(resolve => setTimeout(resolve, 2500));
        }

        // --- ТОТАЛЬНЫЙ ОЧИЩЕННЫЙ СБОР ТЕКСТА ---
        const data = await page.evaluate(() => {
            const nameEl = document.querySelector('h1') || document.querySelector('[class*="title"]');
            const ratingText = document.querySelector('[class*="rating-badge-view__rating-text"]') || document.querySelector('.business-rating-badge-view__rating-text');
            const rating = ratingText ? parseFloat(ratingText.innerText.replace(',', '.')) : 5.0;

            const reviews = [];
            const seenTexts = new Set();

            // Собираем все текстовые ноды карточек отзывов
            const elements = document.querySelectorAll('article, [class*="review-view"], div[class*="review-item"], div[class*="comment"]');

            elements.forEach(el => {
                const authorEl = el.querySelector('[class*="author-name"]') || el.querySelector('h3') || el.querySelector('span[class*="name"]');
                let author_name = authorEl ? authorEl.innerText.trim() : "Пользователь Карт";
                author_name = author_name.replace(/Знаток города.*/g, '').replace(/Подписаться.*/g, '').trim();

                const textBlock = el.querySelector('[class*="text"]') || el.querySelector('p') || el;
                let text = textBlock ? textBlock.innerText.trim() : "";

                text = text.replace(/\.{2,}\s*еще$/ui, '').replace(/\s*еще$/ui, '').trim();

                // Пропускаем только пустые строки
                if (text && text.length > 20 && !seenTexts.has(text)) {
                    seenTexts.add(text);
                    reviews.push({
                        author_name: author_name,
                        text: text
                    });
                }
            });

            return { name: nameEl ? nameEl.innerText.trim() : "Организация Яндекс.Карт", rating, reviews };
        });

        console.log(JSON.stringify(data));

    } catch (error) {
        console.log(JSON.stringify({ error: error.message }));
    } finally {
        if (browser) await browser.close();
        try { fs.unlinkSync(urlFilePath); } catch (e) { }
    }
})();
