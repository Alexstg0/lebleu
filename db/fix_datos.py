import re

# Lee el archivo original sin procesar
with open('/tmp/lebleu_datos_2026-08-26.sql', 'r') as f:
    content = f.read()

# Columnas GENERATED a remover
generated_cols = ['monto_mxn', 'total', 'horas', 'utilidad']

# Por cada columna, remover de INSERTs
for col in generated_cols:
    # Remover ", "col"" del list de columnas
    content = re.sub(f', "{col}"', '', content)
    # Remover "col"," si está al inicio
    content = re.sub(f'"{col}",', '', content)
    # Remover el último "col" si está solo
    content = re.sub(f'"{col}"', '', content)

# Guarda el archivo limpio
with open('datos_20260826_clean.sql', 'w') as f:
    f.write(content)

print("✓ Archivo limpiado")
