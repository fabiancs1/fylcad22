<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:output method="html" encoding="UTF-8" indent="yes"/>

  <xsl:template match="/fylcad-message">
    <html>
      <head>
        <title>FYLCAD - Resultado Topográfico</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 20px; background: #f4f4f4; }
          h2   { color: #2c3e50; }
          table{ border-collapse: collapse; width: 50%; background: white; }
          th   { background: #2c3e50; color: white; padding: 10px; }
          td   { padding: 8px 12px; border: 1px solid #ccc; }
          tr:nth-child(even){ background: #ecf0f1; }
          .error { color: red; font-weight: bold; }
          .footer{ margin-top: 20px; font-size: 12px; color: #888; }
        </style>
      </head>
      <body>
        <h2>FYLCAD — Resultado del Procesamiento Topográfico</h2>

        <p><strong>Estado:</strong> <xsl:value-of select="status"/></p>

        <xsl:if test="data/results">
          <table>
            <tr><th>Métrica</th><th>Valor</th></tr>
            <tr><td>Puntos procesados</td>
                <td><xsl:value-of select="data/results/puntos"/></td></tr>
            <tr><td>Área (m²)</td>
                <td><xsl:value-of select="data/results/area"/></td></tr>
            <tr><td>Volumen (m³)</td>
                <td><xsl:value-of select="data/results/volumen"/></td></tr>
            <tr><td>Cota mínima (m)</td>
                <td><xsl:value-of select="data/results/cota_min"/></td></tr>
            <tr><td>Cota máxima (m)</td>
                <td><xsl:value-of select="data/results/cota_max"/></td></tr>
            <tr><td>Desnivel (m)</td>
                <td><xsl:value-of select="data/results/desnivel"/></td></tr>
          </table>
        </xsl:if>

        <xsl:if test="message">
          <p class="error">
            <strong>Error:</strong> <xsl:value-of select="message"/>
          </p>
        </xsl:if>

        <p class="footer">
          Generado: <xsl:value-of select="control/timestamp"/>
        </p>
      </body>
    </html>
  </xsl:template>

</xsl:stylesheet>