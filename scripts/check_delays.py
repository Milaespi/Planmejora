"""Consulta Supabase y retorna actividades retrasadas de proyectos activos."""
import os
from datetime import date
import requests
import urllib3

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)


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

SUPABASE_URL = os.environ['SUPABASE_URL']
SUPABASE_KEY = os.environ['SUPABASE_KEY']

HEADERS = {
    "apikey":        SUPABASE_KEY,
    "Authorization": f"Bearer {SUPABASE_KEY}",
    "Content-Type":  "application/json",
}


def get_actividades_retrasadas() -> list[dict]:
    """Retorna actividades vencidas de proyectos activos, sin alerta enviada hoy."""
    hoy = date.today().isoformat()

    # Busca actividades pendientes o en progreso cuya fecha estimada ya venció
    url    = f"{SUPABASE_URL}/rest/v1/actividades"
    params = {
        "select":          "id,nombre,estado,fecha_estimada,fase_id,fases(proyecto_id,orden,proyectos(id,nombre,estado))",
        "estado":          "in.(pendiente,en_progreso)",
        "fecha_estimada":  f"lt.{hoy}",
    }
    resp = requests.get(url, headers=HEADERS, params=params, timeout=10, verify=False)
    resp.raise_for_status()
    actividades = resp.json()

    # Filtra: solo proyectos con estado "activo"
    activas = [
        a for a in actividades
        if a.get("fases", {}).get("proyectos", {}).get("estado") == "activo"
    ]

    if not activas:
        return []

    # Excluye las que ya recibieron alerta hoy (evita SMS duplicados)
    ids = [str(a["id"]) for a in activas]
    url_alertas    = f"{SUPABASE_URL}/rest/v1/alertas"
    params_alertas = {
        "select":       "actividad_id",
        "actividad_id": f"in.({','.join(ids)})",
        "enviada":      "eq.true",
        "fecha_envio":  f"gte.{hoy}T00:00:00",
    }
    resp2 = requests.get(url_alertas, headers=HEADERS, params=params_alertas, timeout=10, verify=False)
    resp2.raise_for_status()
    ya_alertadas = {a["actividad_id"] for a in resp2.json()}

    return [a for a in activas if a["id"] not in ya_alertadas]
