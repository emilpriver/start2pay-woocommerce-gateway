# Start2pay payment provider for Wordpress.

This plugin adds an option to Woocommerce to make you be able to make payments via [Start2Pay](https://start2pay.com/).

### Workflow
```bash
    - User fills all information in the checkout(Like normal)
    - When user press buy, will the user be redirected to start2pay payment url
    - Users pay and will be returned to the website.
```

### URLS for webhooks
```bash
    - Success URL = YOURDOMAIN.TLD/wc-api/start2pay_gateway_status 
    - Fail URL = YOURDOMAIN.TLD/wc-api/start2pay_gateway_status 
    - Callback URL = YOURDOMAIN.TLD/wc-api/start2pay_gateway_progress
    - Progress URL = YOURDOMAIN.TLD/wc-api/start2pay_gateway_status 
    - Create Invoice URL = YOURDOMAIN.TLD/wc-api/start2pay_gateway_progress
```

## License
[GNU](https://www.gnu.org/licenses/licenses.html)
