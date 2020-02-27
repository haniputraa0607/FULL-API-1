<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <script>
        setTimeout(() => {
            window.document.getElementById("btn").click()
        }, 500);
    </script>
</head>
<body>
    <form id="clickForm" action="https://ipg.cimbniaga.co.id/BPG/admin/payment/PaymentWindow.jsp" method="post">
        <input type="hidden" id="MERCHANT_ACC_NO" name="MERCHANT_ACC_NO">
        <input type="hidden" id="TXN_PASSWORD" name="TXN_PASSWORD">
        <input type="hidden" id="AMOUNT" name="AMOUNT">
        <input type="hidden" name="TRANSACTION_TYPE" value="2">
        <input type="hidden" id="MERCHANT_TRANID" name="MERCHANT_TRANID">
        <input type="hidden" name="RESPONSE_TYPE" value="HTTP">
        <input type="hidden" id="RETURN_URL" name="RETURN_URL">
        <input type="hidden" name="TXN_DESC" value="Order from Merchant Store">
        <input type="hidden" id="TXN_SIGNATURE" name="TXN_SIGNATURE">
        <button type="submit" style="display: none;" id="btn">submit</button>
    </form>

    <p style="position: fixed; /* or absolute */
    top: 45%;
    left: 50%;
    /* bring your own prefixes */
    transform: translate(-50%, -50%);
    font-size: 20px;">Please Wait...</p>
</body>
</html>