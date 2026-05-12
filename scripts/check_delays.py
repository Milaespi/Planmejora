"""Consulta Supabase y retorna actividades retrasadas de proyectos activos."""
import os
from datetime import date
import requests
from dotenv import load_dotenv

load_dotenv()

SUPABASE_URL = os.getenv("SUPABASE_URL")
SUPABASE_KEY = os.getenv("SUPABASE_KEY")

HEADERS = {
    "apikey": SUPABASE_KEY,
    "Authorization": f"Bearer {SUPABASE_KEY}",
    "Content-Type": "application/json",
}


def get_actividades_retrasadas() -> list[dict]:
    """Retorna actividades vencidas de proyectos activos, sin alerta enviada hoy."""
    hoy = date.today().isoformat()

    # Actividades pendientes o en progreso con fecha estimada vencida
    url = f"{SUPABASE_URL}/rest/v1/actividades"
    params = {
        "select": "id,nombre,estado,fecha_estimada,fase_id,fases(proyecto_id,orden,proyectos(id,nombre,estado))",
        "estado": "in.(pendiente,en_progreso)",
        "fecha_estimada": f"lt.{hoy}",
    }
    resp = requests.get(url, headers=HEADERS, params=params, timeout=10)
    resp.raise_for_status()
    actividades = resp.json()

    # Filtrar: solo proyectos activos
    activas = [
        a for a in actividades
        if a.get("fases", {}).get("proyectos", {}).get("estado") == "activo"
    ]

    # Excluir las que ya tienen alerta enviada hoy
    if not activas:
        return []

    ids = [str(a["id"]) for a in activas]
    url_alertas = f"{SUPABASE_URL}/rest/v1/alertas"
    params_alertas = {
        "select": "actividad_id",
        "actividad_id": f"in.({','.join(ids)})",
        "enviada": "eq.true",
        "fecha_envio": f"gte.{hoy}T00:00:00",
    }
    resp2 = requests.get(url_alertas, headers=HEADERS, params=params_alertas, timeout=10)
    resp2.raise_for_status()
    ya_alertadas = {a["actividad_id"] for a in resp2.json()}

    return [a for a in activas if a["id"] not in ya_alertadas]
