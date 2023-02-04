import { test, expect, APIRequestContext } from '@playwright/test';
import { GetWcApiClient, WcPages } from '@krokedil/wc-test-helper';
import DinteroCheckoutRedirect from '../pages/dintero_checkout_redirect';
import { VerifyOrderRecieved } from '../utils/VerifyOrder';

const {
	BASE_URL,
	CONSUMER_KEY,
	CONSUMER_SECRET,
} = process.env;

const payment_gateway = "dintero_checkout";

test.describe('Guest purchase in a redirect flow', () => {
    test.use({ storageState: process.env.GUESTSTATE });
    let wcApiClient: APIRequestContext;
    let orderId: string
    
    test.beforeAll(async () => {
        wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
    })

    test.afterEach(async () => {
        wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
		await wcApiClient.delete(`orders/${orderId}`);
    })

    test('Can buy 6x 99.99 products with 25% tax.', async ({ page }) => {
        const cartPage = new WcPages.Cart(page, wcApiClient);
        const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
        const checkoutPage = new WcPages.Checkout(page);
        const dinteroCheckoutRedirect = new DinteroCheckoutRedirect(page);
        
        await cartPage.addtoCart(['simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25']);

        await checkoutPage.goto();

        // FIXME: Playwright never seemed to identify the radio button.
        // await checkoutPage.hasPaymentMethodId(payment_gateway);

        await checkoutPage.fillBillingAddress();
        await checkoutPage.placeOrder();
        await dinteroCheckoutRedirect.payAtDintero();
        orderId = await orderRecievedPage.getOrderId();

        // await VerifyOrderRecieved(orderRecievedPage);

    })
})