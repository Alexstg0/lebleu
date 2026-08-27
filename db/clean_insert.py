import re

with open('/tmp/lebleu_datos_2026-08-26.sql', 'r') as f:
    lines = f.readlines()

output = []
for line in lines:
    if 'INSERT INTO' not in line:
        output.append(line)
        continue
    
    # Parse: INSERT INTO "table" ("col1", "col2", ...) VALUES (val1, val2, ...)
    match = re.match(r'(INSERT INTO "[^"]+"\s*)\(([^)]+)\)\s*(VALUES\s*\((.+)\);)', line)
    if not match:
        output.append(line)
        continue
    
    prefix = match.group(1)
    cols_str = match.group(2)
    values_part = match.group(3)
    values_str = match.group(4)
    
    # Split columns y valores
    cols = [c.strip() for c in cols_str.split(',')]
    
    # Identifica índices de columnas a remover
    skip_indices = []
    for i, col in enumerate(cols):
        col_clean = col.strip('"')
        if col_clean in ['monto_mxn', 'total', 'horas', 'utilidad']:
            skip_indices.append(i)
    
    if not skip_indices:
        output.append(line)
        continue
    
    # Remueve columnas
    new_cols = [cols[i] for i in range(len(cols)) if i not in skip_indices]
    
    # Parsea valores (más complejo porque pueden contener comas en strings)
    # Usa split simple asumiendo que los valores están correctamente formateados
    in_string = False
    escape = False
    depth = 0
    vals = []
    current = ''
    
    for char in values_str:
        if escape:
            current += char
            escape = False
            continue
        if char == '\\':
            escape = True
            current += char
            continue
        if char == "'":
            in_string = not in_string
            current += char
            continue
        if not in_string:
            if char == '(':
                depth += 1
                current += char
                continue
            if char == ')':
                depth -= 1
                if depth < 0:
                    vals.append(current)
                    current = ''
                    continue
                current += char
                continue
            if char == ',' and depth == 0:
                vals.append(current)
                current = ''
                continue
        current += char
    if current:
        vals.append(current)
    
    # Remueve valores correspondientes
    new_vals = [vals[i] for i in range(len(vals)) if i not in skip_indices]
    
    new_line = f"{prefix}({', '.join(new_cols)}) VALUES ({', '.join(new_vals)});\n"
    output.append(new_line)

with open('datos_20260826.sql', 'w') as f:
    f.writelines(output)

print("✓ Datos procesados correctamente")
