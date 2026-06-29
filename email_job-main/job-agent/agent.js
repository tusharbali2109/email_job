/**
 * ReachOut Job Agent — Auto-applies on LinkedIn, Naukri, Indeed
 * Run: node agent.js
 * API: http://localhost:3002
 */

const { chromium } = require('playwright');
const express      = require('express');
const cors         = require('cors');
const { createClient } = require('@supabase/supabase-js');

const app  = express();
const PORT = 3002;

app.use(cors({ origin: '*' }));
app.use(express.json());

// ── Supabase ──
const SUPABASE_URL = 'https://wcsckcyxbixcgjrrjoyo.supabase.co';
const SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Indjc2NrY3l4Yml4Y2dqcnJqb3lvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODIyNzA4MTgsImV4cCI6MjA5Nzg0NjgxOH0.YQ_iaVv0HwE1jdQFPN1uYXeqan36TsIs7AY11CPmjTM';
const sb = createClient(SUPABASE_URL, SUPABASE_KEY);

// ── State ──
let agentRunning = false;
let agentLogs    = [];
let agentStats   = { total: 0, applied: 0, skipped: 0, failed: 0, current: null };
let browser      = null;
let stopRequested = false;

function log(msg, type = 'info') {
    const entry = { time: new Date().toISOString(), msg, type };
    agentLogs.unshift(entry);
    if (agentLogs.length > 200) agentLogs.pop();
    console.log(`[${entry.time.slice(11,19)}] ${msg}`);
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

async function randomDelay(min = 1500, max = 4000) {
    await sleep(min + Math.random() * (max - min));
}

// ══════════════════════════════════════════
// LINKEDIN EASY APPLY
// ══════════════════════════════════════════
async function runLinkedIn(page, profile, keywords, maxApply) {
    log('🔷 LinkedIn: Logging in...');
    await page.goto('https://www.linkedin.com/login', { waitUntil: 'domcontentloaded' });
    await randomDelay(1000, 2000);

    // Fill login
    await page.fill('#username', profile.linkedin_email || profile.email);
    await randomDelay(500, 1000);
    await page.fill('#password', profile.linkedin_pass);
    await randomDelay(500, 1000);
    await page.click('[type="submit"]');
    await page.waitForLoadState('networkidle');
    await randomDelay(2000, 4000);

    if (page.url().includes('checkpoint') || page.url().includes('login')) {
        log('⚠️ LinkedIn: CAPTCHA/verification needed — solve it in the browser window, then the agent will continue in 30s', 'warn');
        await sleep(30000);
    }

    log('✅ LinkedIn: Logged in');

    // Search jobs
    const searchUrl = `https://www.linkedin.com/jobs/search/?keywords=${encodeURIComponent(keywords)}&f_AL=true&f_WT=2`;
    await page.goto(searchUrl, { waitUntil: 'domcontentloaded' });
    await randomDelay(2000, 3000);

    let applied = 0;

    while (applied < maxApply && !stopRequested) {
        // Get all Easy Apply job cards
        const jobCards = await page.$$('.jobs-search-results__list-item');
        if (!jobCards.length) break;

        for (const card of jobCards) {
            if (applied >= maxApply || stopRequested) break;

            try {
                await card.click();
                await randomDelay(1500, 2500);

                // Check for Easy Apply button
                const easyApplyBtn = await page.$('button.jobs-apply-button:has-text("Easy Apply")');
                if (!easyApplyBtn) {
                    log('  ⏭ Skip: Not Easy Apply');
                    agentStats.skipped++;
                    continue;
                }

                const jobTitle   = await page.$eval('.job-details-jobs-unified-top-card__job-title', el => el.innerText.trim()).catch(() => 'Unknown');
                const company    = await page.$eval('.job-details-jobs-unified-top-card__company-name', el => el.innerText.trim()).catch(() => 'Unknown');
                const jobUrl     = page.url();

                agentStats.current = `${jobTitle} @ ${company}`;
                log(`  📋 Applying: ${jobTitle} @ ${company}`);

                await easyApplyBtn.click();
                await randomDelay(1500, 2500);

                // Multi-step Easy Apply form
                let stepCount = 0;
                while (stepCount < 10) {
                    await randomDelay(1000, 2000);

                    // Fill phone if asked
                    const phoneInput = await page.$('input[id*="phoneNumber"]');
                    if (phoneInput) {
                        const val = await phoneInput.inputValue();
                        if (!val) await phoneInput.fill(profile.mobile || '');
                    }

                    // Fill city/location if asked
                    const cityInput = await page.$('input[id*="city"]');
                    if (cityInput) {
                        const val = await cityInput.inputValue();
                        if (!val) await cityInput.fill(profile.location || 'India');
                    }

                    // Handle yes/no radio questions — pick "Yes" by default
                    const radioYes = await page.$$('input[type="radio"][value="Yes"], label:has-text("Yes")');
                    for (const r of radioYes) await r.click().catch(() => {});

                    // Handle dropdowns — pick first option
                    const selects = await page.$$('select.fb-dropdown__select');
                    for (const sel of selects) {
                        const opts = await sel.$$('option');
                        if (opts.length > 1) await sel.selectOption({ index: 1 });
                    }

                    // Handle numeric fields (experience years etc)
                    const numInputs = await page.$$('input[type="number"]');
                    for (const ni of numInputs) {
                        const val = await ni.inputValue();
                        if (!val) await ni.fill(profile.experience || '2');
                    }

                    // Next / Submit button
                    const nextBtn   = await page.$('button[aria-label="Continue to next step"]');
                    const reviewBtn = await page.$('button[aria-label="Review your application"]');
                    const submitBtn = await page.$('button[aria-label="Submit application"]');

                    if (submitBtn) {
                        await submitBtn.click();
                        await randomDelay(2000, 3000);
                        log(`  ✅ Applied: ${jobTitle} @ ${company}`, 'success');

                        await sb.from('job_applications').insert({
                            platform: 'linkedin', title: jobTitle, company,
                            job_url: jobUrl, status: 'applied', applied_at: new Date().toISOString()
                        }).catch(() => {});

                        applied++;
                        agentStats.applied++;
                        agentStats.total++;

                        // Close modal
                        const closeBtn = await page.$('button[aria-label="Dismiss"]');
                        if (closeBtn) await closeBtn.click();
                        break;

                    } else if (reviewBtn) {
                        await reviewBtn.click();
                    } else if (nextBtn) {
                        await nextBtn.click();
                    } else {
                        break;
                    }
                    stepCount++;
                }

            } catch (e) {
                log(`  ❌ LinkedIn error: ${e.message}`, 'error');
                agentStats.failed++;
                agentStats.total++;
            }

            await randomDelay(3000, 6000);
        }

        // Next page
        const nextPage = await page.$('button[aria-label="View next page"]');
        if (nextPage && applied < maxApply) {
            await nextPage.click();
            await randomDelay(3000, 5000);
        } else break;
    }

    log(`🔷 LinkedIn done — Applied: ${applied}`);
    return applied;
}

// ══════════════════════════════════════════
// NAUKRI APPLY
// ══════════════════════════════════════════
async function runNaukri(page, profile, keywords, maxApply) {
    log('🟡 Naukri: Logging in...');
    await page.goto('https://www.naukri.com/nlogin/login', { waitUntil: 'domcontentloaded' });
    await randomDelay(2000, 3000);

    await page.fill('input[placeholder="Enter your active Email ID / Username"]', profile.naukri_email || profile.email);
    await randomDelay(500, 1000);
    await page.fill('input[placeholder="Enter your password"]', profile.naukri_pass);
    await randomDelay(500, 1000);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await randomDelay(2000, 4000);

    if (page.url().includes('login')) {
        log('⚠️ Naukri: Login may have failed — check browser window', 'warn');
        await sleep(15000);
    }

    log('✅ Naukri: Logged in');

    // Search
    const searchUrl = `https://www.naukri.com/${encodeURIComponent(keywords.replace(/\s+/g, '-'))}-jobs`;
    await page.goto(searchUrl, { waitUntil: 'domcontentloaded' });
    await randomDelay(2000, 3000);

    let applied = 0;

    const jobLinks = await page.$$eval('a.title', els => els.slice(0, 30).map(e => e.href));
    log(`  Found ${jobLinks.length} jobs on Naukri`);

    for (const link of jobLinks) {
        if (applied >= maxApply || stopRequested) break;
        try {
            await page.goto(link, { waitUntil: 'domcontentloaded' });
            await randomDelay(2000, 3000);

            const jobTitle = await page.$eval('.jd-header-title', el => el.innerText.trim()).catch(() => 'Unknown');
            const company  = await page.$eval('.jd-header-comp-name', el => el.innerText.trim()).catch(() => 'Unknown');

            agentStats.current = `${jobTitle} @ ${company}`;
            log(`  📋 Applying: ${jobTitle} @ ${company}`);

            const applyBtn = await page.$('button#apply-button, button.apply-button, a:has-text("Apply"), button:has-text("Apply Now")');
            if (!applyBtn) { log('  ⏭ No apply button'); agentStats.skipped++; continue; }

            await applyBtn.click();
            await randomDelay(2000, 3000);

            // If new tab opened
            const pages = page.context().pages();
            const applyPage = pages[pages.length - 1];
            await applyPage.waitForLoadState('domcontentloaded').catch(() => {});
            await randomDelay(1500, 2500);

            // Look for submit button
            const submitBtn = await applyPage.$('button:has-text("Apply"), button:has-text("Submit")').catch(() => null);
            if (submitBtn) {
                await submitBtn.click();
                await randomDelay(2000, 3000);
                log(`  ✅ Applied: ${jobTitle} @ ${company}`, 'success');
                await sb.from('job_applications').insert({
                    platform: 'naukri', title: jobTitle, company,
                    job_url: link, status: 'applied', applied_at: new Date().toISOString()
                }).catch(() => {});
                applied++;
                agentStats.applied++;
                if (applyPage !== page) await applyPage.close().catch(() => {});
            } else {
                log('  ⏭ No submit button found');
                agentStats.skipped++;
                if (applyPage !== page) await applyPage.close().catch(() => {});
            }
            agentStats.total++;

        } catch (e) {
            log(`  ❌ Naukri error: ${e.message}`, 'error');
            agentStats.failed++;
            agentStats.total++;
        }
        await randomDelay(4000, 7000);
    }

    log(`🟡 Naukri done — Applied: ${applied}`);
    return applied;
}

// ══════════════════════════════════════════
// INDEED EASY APPLY
// ══════════════════════════════════════════
async function runIndeed(page, profile, keywords, maxApply) {
    log('🔵 Indeed: Logging in...');
    await page.goto('https://secure.indeed.com/account/login', { waitUntil: 'domcontentloaded' });
    await randomDelay(2000, 3000);

    // Step 1: Enter email — wait for visible
    try {
        await page.waitForSelector('input[name="__email"]', { state: 'visible', timeout: 10000 });
        await page.fill('input[name="__email"]', profile.indeed_email || profile.email);
    } catch {
        // fallback selector
        await page.waitForSelector('input[type="email"]', { state: 'visible', timeout: 10000 });
        await page.fill('input[type="email"]', profile.indeed_email || profile.email);
    }
    await randomDelay(800, 1500);

    // Click Continue after email
    await page.locator('button[type="submit"]').first().click();
    await randomDelay(2500, 4000);

    // Step 2: Wait for password field to become visible (it's hidden until Step 1 done)
    log('🔵 Indeed: Waiting for password field...');
    try {
        await page.waitForSelector('input[name="__password"]', { state: 'visible', timeout: 20000 });
        await page.fill('input[name="__password"]', profile.indeed_pass);
        await randomDelay(800, 1500);
        await page.locator('button[type="submit"]').first().click();
        await page.waitForLoadState('networkidle').catch(() => {});
        await randomDelay(3000, 5000);
    } catch {
        log('⚠️ Indeed: Password field timed out — CAPTCHA/OTP ho sakta hai. 60s mein manually login karo.', 'warn');
        await sleep(60000);
    }

    if (page.url().includes('login') || page.url().includes('auth')) {
        log('⚠️ Indeed: Still on login page — 30s extra wait', 'warn');
        await sleep(30000);
    }

    log('✅ Indeed: Login done');

    const searchUrl = `https://in.indeed.com/jobs?q=${encodeURIComponent(keywords)}&l=India&iafilter=1`;
    await page.goto(searchUrl, { waitUntil: 'domcontentloaded' });
    await randomDelay(2000, 3000);

    let applied = 0;
    const jobCards = await page.$$('.job_seen_beacon');
    log(`  Found ${jobCards.length} jobs on Indeed`);

    for (const card of jobCards) {
        if (applied >= maxApply || stopRequested) break;
        try {
            await card.click();
            await randomDelay(2000, 3000);

            const jobTitle = await page.$eval('.jobsearch-JobInfoHeader-title', el => el.innerText.trim()).catch(() => 'Unknown');
            const company  = await page.$eval('[data-company-name="true"]', el => el.innerText.trim()).catch(() => 'Unknown');
            const jobUrl   = page.url();

            agentStats.current = `${jobTitle} @ ${company}`;

            const applyBtn = await page.$('button:has-text("Easily apply"), button:has-text("Apply now"), .ia-IndeedApplyButton');
            if (!applyBtn) { log('  ⏭ No Easy Apply'); agentStats.skipped++; continue; }

            log(`  📋 Applying: ${jobTitle} @ ${company}`);
            await applyBtn.click();
            await randomDelay(2000, 3000);

            // Indeed apply modal/page
            let stepCount = 0;
            while (stepCount < 8) {
                await randomDelay(1500, 2500);

                const contBtn   = await page.$('button:has-text("Continue"), button[data-testid="continue-button"]');
                const submitBtn = await page.$('button:has-text("Submit your application"), button[data-testid="submit-application-button"]');

                if (submitBtn) {
                    await submitBtn.click();
                    await randomDelay(2000, 3000);
                    log(`  ✅ Applied: ${jobTitle} @ ${company}`, 'success');
                    await sb.from('job_applications').insert({
                        platform: 'indeed', title: jobTitle, company,
                        job_url: jobUrl, status: 'applied', applied_at: new Date().toISOString()
                    }).catch(() => {});
                    applied++;
                    agentStats.applied++;
                    agentStats.total++;
                    break;
                } else if (contBtn) {
                    await contBtn.click();
                } else break;

                stepCount++;
            }

        } catch (e) {
            log(`  ❌ Indeed error: ${e.message}`, 'error');
            agentStats.failed++;
            agentStats.total++;
        }
        await randomDelay(4000, 7000);
    }

    log(`🔵 Indeed done — Applied: ${applied}`);
    return applied;
}

// ══════════════════════════════════════════
// MAIN AGENT RUNNER
// ══════════════════════════════════════════
async function runAgent(config) {
    agentRunning  = true;
    stopRequested = false;
    agentLogs     = [];
    agentStats    = { total: 0, applied: 0, skipped: 0, failed: 0, current: null };

    const { profile, keywords, maxPerPlatform = 10, platforms } = config;

    log('🚀 Job Agent STARTED');
    log(`Keywords: "${keywords}" | Max per platform: ${maxPerPlatform}`);
    log(`Platforms: ${platforms.join(', ')}`);

    try {
        browser = await chromium.launch({
            headless: false,
            args: ['--start-maximized'],
            slowMo: 100
        });

        const context = await browser.newContext({
            viewport: { width: 1280, height: 800 },
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        });

        const page = await context.newPage();

        if (platforms.includes('linkedin') && !stopRequested) {
            await runLinkedIn(page, profile, keywords, maxPerPlatform).catch(e => log(`LinkedIn failed: ${e.message}`, 'error'));
        }

        if (platforms.includes('naukri') && !stopRequested) {
            await runNaukri(page, profile, keywords, maxPerPlatform).catch(e => log(`Naukri failed: ${e.message}`, 'error'));
        }

        if (platforms.includes('indeed') && !stopRequested) {
            await runIndeed(page, profile, keywords, maxPerPlatform).catch(e => log(`Indeed failed: ${e.message}`, 'error'));
        }

        await browser.close();
        browser = null;

    } catch (e) {
        log(`❌ Agent error: ${e.message}`, 'error');
        if (browser) { await browser.close().catch(() => {}); browser = null; }
    }

    agentStats.current = null;
    agentRunning = false;
    log(`🏁 Agent DONE — Total: ${agentStats.total} | Applied: ${agentStats.applied} | Skipped: ${agentStats.skipped} | Failed: ${agentStats.failed}`, 'success');
}

// ══════════════════════════════════════════
// API ENDPOINTS
// ══════════════════════════════════════════
app.get('/status', (req, res) => res.json({ running: agentRunning, stats: agentStats, logs: agentLogs.slice(0, 50) }));

app.post('/start', async (req, res) => {
    if (agentRunning) return res.status(400).json({ success: false, error: 'Agent already running' });

    const { keywords, maxPerPlatform, platforms, profile } = req.body;
    if (!keywords || !profile) return res.status(400).json({ success: false, error: 'keywords and profile required' });

    res.json({ success: true, message: 'Agent starting...' });
    runAgent({ keywords, maxPerPlatform: maxPerPlatform || 10, platforms: platforms || ['linkedin','naukri','indeed'], profile });
});

app.post('/stop', async (req, res) => {
    stopRequested = true;
    log('⛔ Stop requested by user', 'warn');
    res.json({ success: true });
});

app.get('/logs', (req, res) => res.json(agentLogs));
app.get('/applications', async (req, res) => {
    const { data } = await sb.from('job_applications').select('*').order('applied_at', { ascending: false }).limit(100);
    res.json(data || []);
});

app.listen(PORT, () => console.log(`\n🤖 Job Agent running at http://localhost:${PORT}\n`));
