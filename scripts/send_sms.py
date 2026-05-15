"""Envía un SMS al supervisor usando Twilio."""
import os
import urllib3
import requests
from twilio.rest import Client


def _load_env() -> None:
    """Carga el archivo .env si existe (en desarrollo local sin Railway)."""
    env_path = os.path.join(os.path.dirname(__file__), '..', '.env')
    if not os.path.exists(env_path):
        return
    with open(env_path) as f:
        for line in f:
            line = line.strip()
            if line and not line.startswith('#') and '=' in line:
                k, v = line.split('=', 1)
                os.environ.setdefault(k.strip(), v.strip())


_load_env()

# En desarrollo local (XAMPP/Windows) no hay CA bundle — desactiva SSL verification
# En producción (Linux/Railway) esto no se ejecuta
if os.name == 'nt':
    urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
    _orig = requests.Session.request
    def _no_ssl(self, *args, **kwargs):
        kwargs['verify'] = False
        return _orig(self, *args, **kwargs)
    requests.Session.request = _no_ssl

ACCOUNT_SID      = os.environ['TWILIO_ACCOUNT_SID']
AUTH_TOKEN       = os.environ['TWILIO_AUTH_TOKEN']
TWILIO_PHONE     = os.environ['TWILIO_PHONE']
SUPERVISOR_PHONE = os.environ['SUPERVISOR_PHONE']


def send_sms(mensaje: str, destinatario: str = SUPERVISOR_PHONE) -> str:
    """Envía el mensaje SMS y retorna el SID de Twilio."""
    client = Client(ACCOUNT_SID, AUTH_TOKEN)
    message = client.messages.create(
        body=mensaje,
        from_=TWILIO_PHONE,
        to=destinatario,
    )
    return message.sid


def formatear_mensaje(actividad: dict) -> str:
    """Construye el texto del SMS con los datos de la actividad retrasada."""
    fase = actividad.get("fases", {})
    proyecto = fase.get("proyectos", {})
    nombre_fase = "Fase 1 - Obra Blanca" if fase.get("orden") == 1 else "Fase 2 - Amueblamiento"

    return (
        f"RETRASO EN OBRA\n"
        f"Proyecto: {proyecto.get('nombre', 'Sin nombre')}\n"
        f"Actividad: {actividad['nombre']} ({nombre_fase})\n"
        f"Fecha limite: {actividad['fecha_estimada']}\n"
        f"Estado: {actividad['estado']}\n"
        f"-- Sistema Planmejora"
    )
