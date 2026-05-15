"""Script principal de alertas — detecta actividades retrasadas y envía SMS."""
import logging
from datetime import datetime
import requests
import urllib3
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

from check_delays import get_actividades_retrasadas, SUPABASE_URL, HEADERS
from send_sms import send_sms, formatear_mensaje

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
log = logging.getLogger(__name__)


def registrar_alerta(actividad_id: int, mensaje: str) -> None:
    """Guarda en Supabase un registro del SMS enviado para no repetirlo hoy."""
    url = f"{SUPABASE_URL}/rest/v1/alertas"
    payload = {
        "actividad_id": actividad_id,
        "mensaje":      mensaje,
        "enviada":      True,
        "fecha_envio":  datetime.utcnow().isoformat() + "Z",
    }
    resp = requests.post(url, headers={**HEADERS, "Prefer": "return=minimal"}, json=payload, timeout=10, verify=False)
    resp.raise_for_status()


def main() -> None:
    log.info("=== Inicio de detección de retrasos ===")

    try:
        retrasadas = get_actividades_retrasadas()
    except Exception as exc:
        log.error("Error al consultar actividades: %s", exc)
        return

    if not retrasadas:
        log.info("Sin actividades retrasadas. Fin del proceso.")
        return

    log.info("Actividades retrasadas encontradas: %d", len(retrasadas))

    enviadas = 0
    errores  = 0

    for actividad in retrasadas:
        try:
            mensaje = formatear_mensaje(actividad)
            sid     = send_sms(mensaje)
            log.info("SMS enviado — actividad_id=%d SID=%s", actividad["id"], sid)
            registrar_alerta(actividad["id"], mensaje)
            enviadas += 1
        except Exception as exc:
            log.error("Error al procesar actividad_id=%d: %s", actividad["id"], exc)
            errores += 1

    log.info("=== Fin del proceso: %d enviados, %d errores ===", enviadas, errores)


if __name__ == "__main__":
    main()
