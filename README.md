# OpenCart 3.x versions

## Installation

1) Download apirone-opencart.ocmod.zip
2) Go to Extensions » Installer and upload apirone-opencart.ocmod.zip
3) Go to Extensions » Extensions. Choose Payments from dropdown menu.
4) Click install button (green plus) Apirone plugin.
5) Click Edit button.
6) Enter your Bitcoin address and switch plugin Status to enable in Plugin settings.

PS: Do not forget to clear cache at dashboard page. See attached pictures.


## How does it work?

https://image.opencart.com/original/5af5776710e75.jpg

The Buyer prepared the order and click to pay via bitcoins.
The Store sends bitcoin address and site callback URL to Apirone API Server.
The Store receives new bitcoin address, QR code and converted the amount to BTC for payment.
Buyer scan QR code and pay for the order. This transaction goes to the blockchain.

* Our Server immediately got it and send a callback to supplied Store URL. Now it’s first callback about the unconfirmed transaction. It’s too early to pass order from Store to Buyer. We just notify that payment initiated.
* Waiting for payment confirmation on the network. Usually, it will take about ten minutes.
* Got it. After 1 confirmation our Server forward confirmed bitcoins to Store’s destination address and do the second callback. Now the Buyer gets the desired order.
* Store finished order and ready for next customers.

The plugin uses our own RESTful API – Bitcoin Forwarding. You can read more “How does it work” at https://apirone.com/docs/how-it-works and details about bitcoin forwarding. Site support multi-language documentation.

## Everyone can accept bitcoin payments!

Contact info:
Site: https://apirone.com/
Email: support@apirone.com
