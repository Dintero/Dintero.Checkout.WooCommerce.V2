import { Locator, Page } from '@playwright/test';

export default class DinteroCheckoutRedirect {
    readonly page: Page;
    readonly buyButton: Locator;

    constructor(page: Page) {
        this.page = page;
        this.buyButton = page.locator('button#buy-button');
    }

    async payAtDintero() {
        await this.page.waitForLoadState('networkidle');
        
        await this.page.locator('label').filter({ hasText: 'Credit Card - credit or debit' }).click()

        const iframe = this.page.frameLocator('iframe[title="Pay by Card"]');
        await iframe.locator('#panInput').fill('4111111111111111');
        await iframe.locator('#expiryInput').fill('12/25');
        await iframe.locator('#cvcInput-1').fill('123');
        await iframe.locator('#px-submit').click();
    }

    async placeOrder() {
        await this.page.waitForLoadState('networkidle');
        await this.buyButton.click();
    }
}