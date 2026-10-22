// Lokasi folder assets/ dihitung otomatis dari lokasi file ini
const FA_BASE = document.currentScript
    ? new URL('.', document.currentScript.src).href
    : 'assets/';

const FA = {
    LOCAL_MODEL: FA_BASE + 'models/',
    CDN_MODEL: 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/model/',

    VIDEO_WIDTH: 640,
    VIDEO_HEIGHT: 480,
    INPUT_SIZE: 320,        // 160 / 224 / 320 / 416 (besar = lebih akurat, lebih lambat)
    SCORE_THRESHOLD: 0.5,
    MIN_FACE_RATIO: 0.2,    // lebar wajah minimal 20% dari lebar video

    _loaded: false,
    _loading: null,

    _opt() {
        return new faceapi.TinyFaceDetectorOptions({
            inputSize: this.INPUT_SIZE,
            scoreThreshold: this.SCORE_THRESHOLD
        });
    },

    async _loadModels() {
        if (this._loaded) return;
        if (this._loading) return this._loading;
        const from = (u) => Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(u),
            faceapi.nets.faceLandmark68Net.loadFromUri(u),
            faceapi.nets.faceRecognitionNet.loadFromUri(u)
        ]);
        this._loading = (async () => {
            try {
                await from(this.LOCAL_MODEL);
            } catch (err) {
                console.warn('Model lokal tidak ditemukan, memakai CDN.');
                await from(this.CDN_MODEL);
            }
            this._loaded = true;
        })();
        try { await this._loading; } finally { this._loading = null; }
    },

    async _startCamera(v) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Kamera hanya jalan di localhost atau HTTPS.');
        }
        if (v.srcObject) return;
        v.muted = true;
        v.playsInline = true;
        v.srcObject = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'user',
                width: { ideal: this.VIDEO_WIDTH },
                height: { ideal: this.VIDEO_HEIGHT }
            },
            audio: false
        });
        await v.play();
        if (v.readyState < 2) {
            await new Promise((r) => v.addEventListener('loadeddata', r, { once: true }));
        }
    },

    async load(v) {
        // model dan kamera dimuat bersamaan
        await Promise.all([this._loadModels(), this._startCamera(v)]);
        // warm-up: deteksi pertama selalu paling lambat
        try { await faceapi.detectSingleFace(v, this._opt()); } catch (err) { }
    },

    stop(v) {
        if (v && v.srcObject) {
            v.srcObject.getTracks().forEach((t) => t.stop());
            v.srcObject = null;
        }
    },

    // Mengembalikan array 128 angka, atau null jika wajah tidak terdeteksi
    async desc(v) {
        if (!this._loaded || !v || v.readyState < 2 || !v.videoWidth) return null;
        const r = await faceapi
            .detectSingleFace(v, this._opt())
            .withFaceLandmarks()
            .withFaceDescriptor();
        if (!r) return null;
        if (r.detection.box.width < v.videoWidth * this.MIN_FACE_RATIO) return null; // terlalu jauh
        return Array.from(r.descriptor);
    },

    // Ambil n frame, rata-ratakan. Lebih akurat daripada satu frame.
    async descAvg(v, n = 3, jeda = 120) {
        const list = [];
        for (let i = 0; i < n; i++) {
            const d = await this.desc(v);
            if (d) list.push(d);
            if (i < n - 1) await new Promise((r) => setTimeout(r, jeda));
        }
        if (list.length < Math.ceil(n / 2)) return null; // minimal separuh frame berhasil
        return list[0].map((_, k) => list.reduce((s, d) => s + d[k], 0) / list.length);
    }
};