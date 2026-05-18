const { Client, LocalAuth } = require('whatsapp-web.js');
const express = require('express');
const qrcode = require('qrcode-terminal');

const app = express();
app.use(express.json());

const client = new Client({
    authStrategy: new LocalAuth(),
    puppeteer: {
        headless: true,
        args: ['--no-sandbox']
    }
});

client.on('qr', qr => {
    console.log('🟨 Escaneie o QR Code abaixo com seu WhatsApp:');
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    console.log('✅ Bot do WhatsApp está conectado!');
});

client.initialize();

app.post('/enviar', async (req, res) => {
    const { numero, mensagem } = req.body;

    if (!numero || !mensagem) {
        return res.status(400).send({ status: 'erro', mensagem: 'Número ou mensagem ausente.' });
    }

    const destino = numero.replace(/\D/g, '') + '@c.us';

    try {
        await client.sendMessage(destino, mensagem);
        res.send({ status: 'sucesso', numero, mensagem });
    } catch (error) {
        res.status(500).send({ status: 'erro', erro: error.message });
    }
});

const PORTA = 3000;
app.listen(PORTA, () => {
    console.log(`🚀 API rodando em http://localhost:${PORTA}/enviar`);
});

