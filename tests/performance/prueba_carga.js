import http from 'k6/http';
import { check, sleep } from 'k6';

// Configuración de la carga (¡200 USUARIOS!)
export const options = {
    stages: [
        { duration: '10s', target: 20 },  // Calentamiento un poco más rápido
        { duration: '40s', target: 200 }, // Carga PESADA: 200 usuarios golpeando a la vez
        { duration: '10s', target: 0 },   // Enfriamiento
    ],
    thresholds: {
        // Relajé un poco el umbral a 2s porque 200 usuarios en local es brutal
        http_req_duration: ['p(95)<2000'], 
    },
};

// 1. SETUP: Se ejecuta UNA vez al principio para obtener el Token
export function setup() {
    const baseUrl = 'http://localhost'; 
    
    const payload = JSON.stringify({
        email: 'zs20015694@estudiantes.uv.mx', 
    });

    const params = {
        headers: { 'Content-Type': 'application/json' },
    };

    const loginRes = http.post(`${baseUrl}/api/test/login-k6`, payload, params);

    if (loginRes.status !== 200) {
        console.error('❌ Error en Setup: ' + loginRes.status);
        console.error('📄 Respuesta del servidor: ' + loginRes.body);
    }

    check(loginRes, {
        'Login falso exitoso': (r) => r.status === 200,
        'Tenemos token': (r) => r.json('access_token') !== undefined,
    });

    return { token: loginRes.json('access_token') };
}

// 2. DEFAULT: Lo que hace cada usuario virtual
export default function (data) {
    const baseUrl = 'http://localhost';
    
    // Inyectamos el token en la cabecera
    const params = {
        headers: {
            'Authorization': `Bearer ${data.token}`,
            'Accept': 'application/json',
        },
    };

    // Escenario: Ver lista de solicitudes
    const res = http.get(`${baseUrl}/api/solicitudes`, params);

    check(res, {
        'Status es 200': (r) => r.status === 200,
    });

    // Sleep aleatorio entre 0.5 y 1.5s para que no todos golpeen EXACTAMENTE al mismo milisegundo
    sleep(Math.random() * 1 + 0.5);
}