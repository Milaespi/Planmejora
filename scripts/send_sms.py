"""Envía un SMS al supervisor usando Twilio."""
import os
from twilio.rest import Client
from dotenv import load_dotenv

load_dotenv()

ACCOUNT_SID      = os.getenv("TWILIO_ACCOUNT_SID")
AUTH_TOKEN       = os.getenv("TWILIO_AUTH_TOKEN")
TWILIO_PHONE     = os.getenv("TWILIO_PHONE")
SUPERVISOR_PHONE = os.getenv("SUPERVISOR_PHONE")


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
    fase = actividad.get("fases", {})
    proyecto = fase.get("proyectos", {})
    nombre_fase = "Fase 1 - Obra Blanca" if fase.get("orden") == 1 else "Fase 2 - Amueblamiento"

    return (
        f"⚠ RETRASO EN OBRA\n"
        f"Proyecto: {proyecto.get('nombre', 'Sin nombre')}\n"
        f"Actividad: {actividad['nombre']} ({nombre_fase})\n"
        f"Fecha límite: {actividad['fecha_estimada']}\n"
        f"Estado: {actividad['estado']}\n"
        f"-- Sistema Planmejora"
    )
