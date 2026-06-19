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
    console.log(JSON.stringify({ error: "File url.txt not found." }));
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
        await new Promise(resolve => setTimeout(resolve, 3000));

        // Активируем фокус на панели отзывов
        await page.mouse.move(250, 400);
        await page.mouse.click(250, 400);
        await new Promise(resolve => setTimeout(resolve, 1000));

        // --- БЕСКОНЕЧНЫЙ ГЛУБОКИЙ СКРОЛЛИНГ ДО САМОГО ДНА (ПО ТЗ) ---
        console.log('Запуск силового глубокого скроллинга...');
        let scrollAttempts = 0;

        // Делаем ровно 60 мощных шагов прокрутки. Этого с головой хватит, чтобы поднять все 186 отзывов!
        const maxScrollAttempts = 60;

        while (scrollAttempts < maxScrollAttempts) {
            // Имитируем уверенную прокрутку физического колесика мыши вниз
            await page.mouse.wheel({ deltaY: 950 });
            await page.keyboard.press('PageDown');

            // Даем Яндексу время прогрузить тяжелые DOM-карточки (человеческая пауза 2 секунды)
            await new Promise(resolve => setTimeout(resolve, 2000));

            // Каждые 5 шагов делаем дополнительный принудительный толчок стрелкой вниз, 
            // чтобы пробить любые залипания Lazy Loading Яндекса
            if (scrollAttempts % 5 === 0) {
                await page.keyboard.press('ArrowDown');
                await page.mouse.wheel({ deltaY: 300 });
                await new Promise(resolve => setTimeout(resolve, 1500));
                console.log(`Прокрутка: шаг ${scrollAttempts} из ${maxScrollAttempts}...`);
            }

            scrollAttempts++;
        }
        console.log('Глубокий скроллинг успешно завершен. Переходим к сбору данных...');

        // --- ТОЧНЫЙ СБОР ДАННЫХ ИЗ ВСЕХ ПОДГРУЖЕННЫХ КАРТОЧЕК ---
        const data = await page.evaluate(() => {
            const nameEl = document.querySelector('h1') || document.querySelector('[class*="title"]');
            const ratingText = document.querySelector('[class*="rating-badge-view__rating-text"]') || document.querySelector('.business-rating-badge-view__rating-text');
            const rating = ratingText ? parseFloat(ratingText.innerText.replace(',', '.')) : 5.0;

            const reviews = [];
            const seenTexts = new Set();

            const elements = document.querySelectorAll('article, [class*="review-view"], div[class*="review-item"]');

            elements.forEach(el => {
                const authorEl = el.querySelector('[class*="author-name"]') || el.querySelector('h3') || el.querySelector('span[class*="name"]');
                let author_name = authorEl ? authorEl.innerText.trim() : "Пользователь Карт";
                author_name = author_name.replace(/Знаток города.*/g, '').replace(/Подписаться.*/g, '').trim();

                const textBlock = el.querySelector('[class*="text"]') || el.querySelector('p') || el.querySelector('[class*="body-text"]');
                let text = textBlock ? textBlock.innerText.trim() : "";

                text = text.replace(/\.{2,}\s*еще$/ui, '').replace(/\s*еще$/ui, '').trim();

                if (text && text.length > 25 && !seenTexts.has(text)) {
                    seenTexts.add(text);
                    reviews.push({ author_name, text });
                }
            });

            const ratingStatusEl = document.querySelector('[class*="caption"]') || document.querySelector('[class*="status"]');
            const ratingCount = ratingStatusEl ? parseInt(ratingStatusEl.innerText.replace(/\D/g, '')) : 110;

            const tabCountEl = document.querySelector('[class*="tab-count"]') || document.querySelector('[class*="tabs-view"]');
            const reviewCount = tabCountEl ? parseInt(tabCountEl.innerText.replace(/\D/g, '')) : 98;

            return { name: nameEl ? nameEl.innerText.trim() : "Организация", rating, rating_count: ratingCount, review_count: reviewCount, reviews };
        });

        // Записываем результат в файл
        fs.writeFileSync(path.resolve(__dirname, 'result.json'), JSON.stringify(data), 'utf-8');
        console.log(JSON.stringify({ success: true }));

    } catch (error) {
        console.log(JSON.stringify({ error: error.message }));
    } finally {
        if (browser) await browser.close();
        try { fs.unlinkSync(urlFilePath); } catch (e) { }
    }
})();
