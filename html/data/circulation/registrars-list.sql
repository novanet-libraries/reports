SELECT HOME_LIBRARY, COALESCE(l.EXPIRY_DATE, n.EXPIRY_DATE) EXPIRY_DATE, NAME, AMOUNT, BARCODE, INST_ID
  FROM WEBREPORT.PATRON_GLOBAL           g
  JOIN WEBREPORT.CASH_OWING_PER_PATRON   c ON (g.USER_ID = c.USER_ID)
  LEFT OUTER JOIN WEBREPORT.PATRON_LOCAL l ON (FKZ305 = l.Z305_REC_KEY)
  LEFT OUTER JOIN WEBREPORT.PATRON_LOCAL n ON (g.USER_ID || 'NOV50' = n.Z305_REC_KEY)
  LEFT OUTER JOIN WEBREPORT.PATRON_IDS   i ON (g.USER_ID = i.USER_ID)
 WHERE HOME_LIBRARY = :SUBLIBRARY
   AND COALESCE(l.PATRON_STATUS, n.PATRON_STATUS) IN ('01','02','03','28','29','32','35','37','42','44','48','53','62','70','82','85','96','97','98')
   AND AMOUNT <> 0
