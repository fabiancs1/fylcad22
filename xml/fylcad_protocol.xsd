<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">

  <xs:element name="fylcad-message">
    <xs:complexType>
      <xs:sequence>
        <xs:element name="operation" type="xs:string" minOccurs="0"/>
        <xs:element name="status"    type="xs:string" minOccurs="0"/>
        <xs:element name="message"   type="xs:string" minOccurs="0"/>
        <xs:element name="data"      minOccurs="0">
          <xs:complexType>
            <xs:sequence>
              <xs:element name="project_id" type="xs:integer" minOccurs="0"/>
              <xs:element name="points" minOccurs="0">
                <xs:complexType>
                  <xs:sequence>
                    <xs:element name="point" maxOccurs="unbounded">
                      <xs:complexType>
                        <xs:sequence>
                          <xs:element name="x" type="xs:decimal"/>
                          <xs:element name="y" type="xs:decimal"/>
                          <xs:element name="z" type="xs:decimal"/>
                        </xs:sequence>
                      </xs:complexType>
                    </xs:element>
                  </xs:sequence>
                </xs:complexType>
              </xs:element>
              <xs:element name="results" minOccurs="0">
                <xs:complexType>
                  <xs:sequence>
                    <xs:element name="distance" type="xs:decimal" minOccurs="0"/>
                    <xs:element name="area"     type="xs:decimal" minOccurs="0"/>
                    <xs:element name="volumen"  type="xs:decimal" minOccurs="0"/>
                    <xs:element name="cota_min" type="xs:decimal" minOccurs="0"/>
                    <xs:element name="cota_max" type="xs:decimal" minOccurs="0"/>
                    <xs:element name="desnivel" type="xs:decimal" minOccurs="0"/>
                    <xs:element name="puntos"   type="xs:integer" minOccurs="0"/>
                    <xs:element name="mensaje"  type="xs:string"  minOccurs="0"/>
                  </xs:sequence>
                </xs:complexType>
              </xs:element>
            </xs:sequence>
          </xs:complexType>
        </xs:element>
        <xs:element name="control" minOccurs="0">
          <xs:complexType>
            <xs:sequence>
              <xs:element name="timestamp" type="xs:string"/>
              <xs:element name="client_id" type="xs:string" minOccurs="0"/>
            </xs:sequence>
          </xs:complexType>
        </xs:element>
      </xs:sequence>
    </xs:complexType>
  </xs:element>

</xs:schema>