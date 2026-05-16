import QRCode from 'qrcode';

const canvas = document.getElementById('btc-qr');
if (canvas) {
    QRCode.toCanvas(canvas, canvas.dataset.address, {
        width: 200,
        margin: 2,
    });
}
