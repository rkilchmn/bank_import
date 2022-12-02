Sub Button1_Click()

    ' cells with header and data
    Const rowHeader As Long = 3
    Const rowFirst As Long = 4
    
    ' header for new table
    Const colQuickType As Long = 1
    Const colDate As Long = 2
    Const colCurrency As Long = 3
    Const colAmount As Long = 4
    Const colMemo As Long = 5
    
    
    Dim wsCurrent, wsNew As Worksheet
    Dim tbl As ListObject
    With ActiveWorkbook
        ' remember current ws
        Set wsCurrent = .ActiveSheet
        Sheets.Add after:=.Sheets(.Sheets.Count)
        Set wsNew = .ActiveSheet
        Set tbl = wsNew.ListObjects.Add
        tbl.Name = "QuickEntries"
        tbl.ListColumns.Item(colQuickType).Name = "Quick Entry Type"
        tbl.ListColumns.Add(colDate).Name = "Date"
        tbl.ListColumns.Add(colCurrency).Name = "Currency"
        tbl.ListColumns.Add(colAmount).Name = "Amount"
        tbl.ListColumns.Add(colMemo).Name = "Memo"
        wsCurrent.Activate
    End With

    Set ColumnHeader = CreateObject("System.Collections.ArrayList")
    ' get column lables
    Dim col As Long
    col = 1
    
    With ActiveSheet
        Do Until IsEmpty(Cells(rowHeader, col))
            ColumnHeader.Add (Cells(rowHeader, col).Value)
            col = col + 1
        Loop
    End With

    ' loop through rows
    Dim rowData As Object 'Declare a generic Object reference
    Set rowData = CreateObject("Scripting.Dictionary") 'Late Binding of the Dictionary
    
    Dim row As Long
    row = rowFirst
    With ActiveSheet
        Do Until IsEmpty(Cells(row, 1))
            rowData.RemoveAll
            For col = 1 To ColumnHeader.Count
                rowData.Add ColumnHeader(col - 1), Cells(row, col).Value
            Next col
            row = row + 1
            
            Dim quantityPriceStr As String
            Dim avgPrice As Variant: avgPrice = CDec(0.6)
            
            Select Case rowData("Asset Category")
                Case "Equity and Index Options"
                    ' price information
                    If (rowData("Sum of Quantity") <> 0) Then
                        avgPrice = Abs(rowData("Sum of Proceeds") / (rowData("Sum of Quantity") * 100)) ' equity option is for lots of 100 stocks
                        quantityPriceStr = "#" + CStr(Abs(rowData("Sum of Quantity")))
                        quantityPriceStr = quantityPriceStr + " @" + Format(avgPrice, "###0.00")
                        quantityPriceStr = quantityPriceStr + " " + rowData("Currency")
                    End If
                    
                    Dim memoStr As String

                    Select Case rowData("Code")
                        Case "O", "C"
                            If rowData("Sum of Proceeds") <> 0 Then
                                ' add option transaction
                                Dim newRow As ListRow
                                Set newRow = tbl.ListRows.Add
                                With newRow
                                    .Range(colDate) = rowData("Date")
                                    .Range(colCurrency) = rowData("Currency")
                                    .Range(colDate).NumberFormat = "mm/dd/yyyy" ' frontaccounting US date format
                                    .Range(colAmount) = Format(Abs(rowData("Sum of Proceeds")), "###0.00")
                                    
                                    If rowData("Sum of Quantity") < 0 Then
                                        ' sell (write) option
                                        .Range(colQuickType) = "Option Premium Received"
                                        memoStr = "Sell " + LCase(rowData("Put/Call")) + " " + rowData("Symbol") + " " + quantityPriceStr
                                    ElseIf rowData("Sum of Quantity") > 0 Then
                                        ' buy back option or buy put
                                        .Range(colQuickType) = "Option Premium Paid"
                                        memoStr = "Buy " + LCase(rowData("Put/Call")) + " " + rowData("Symbol") + " " + quantityPriceStr
                                     End If
                                    .Range(colMemo) = memoStr
                                End With
                            End If
                            
                            If rowData("Sum of Comm/Fee") <> 0 Then
                                ' add commission transaction
                                Set newRow = tbl.ListRows.Add
                                With newRow
                                    .Range(colDate) = rowData("Date")
                                    .Range(colDate).NumberFormat = "mm/dd/yyyy"
                                    .Range(colMemo) = rowData("Symbol")
                                    .Range(colCurrency) = rowData("Currency")
                                    .Range(colAmount) = Format(Abs(rowData("Sum of Comm/Fee")), "###0.00")
                                    .Range(colMemo) = memoStr
                                    
                                    If rowData("Sum of Comm/Fee") < 0 Then
                                        .Range(colQuickType) = "Option Broker Commission Paid"
                                    ElseIf rowData("Sum of Comm/Fee") > 0 Then
                                        .Range(colQuickType) = "Option Broker Commission Received"
                                    End If
                                End With
                            End If
                        Case "A;C"
                            If (rowData("Put/Call") = "PUT") Then
                                ' put was assigned
                                Set newRow = tbl.ListRows.Add
                                With newRow
                                    .Range(colDate) = rowData("Date")
                                    .Range(colDate).NumberFormat = "mm/dd/yyyy"
                                    .Range(colMemo) = rowData("Symbol")
                                    .Range(colCurrency) = rowData("Currency")
                                    .Range(colAmount) = "<premium earned for number of assigned puts>"
                                    .Range(colQuickType) = "Option Assigned Stock Premium Transfer"
                                    .Range(colMemo) = "Assignment of stock " + CStr(rowData("Underlying")) + " #" + CStr(Abs(rowData("Sum of Quantity") * 100)) + " for put " + CStr(rowData("Symbol")) + " " + CStr(Abs(rowData("Sum of Quantity")))
                                End With
                            End If
                    End Select
                Case "Stocks"
                    ' price information
                    If (rowData("Sum of Quantity") <> 0) Then
                        avgPrice = Abs(rowData("Sum of Proceeds") / rowData("Sum of Quantity")) ' equity option is for lots of 100 stocks
                        quantityPriceStr = "#" + CStr(Abs(rowData("Sum of Quantity")))
                        quantityPriceStr = quantityPriceStr + " @" + Format(avgPrice, "###0.00")
                        quantityPriceStr = quantityPriceStr + " " + rowData("Currency")
                    End If
                    Select Case rowData("Code")
                        Case "A;O"
                            If rowData("Sum of Quantity") > 0 Then
                                Set newRow = tbl.ListRows.Add
                                    With newRow
                                        .Range(colDate) = rowData("Date")
                                        .Range(colDate).NumberFormat = "mm/dd/yyyy"
                                        .Range(colMemo) = rowData("Symbol")
                                        .Range(colCurrency) = rowData("Currency")
                                        .Range(colAmount) = Format(Abs(rowData("Sum of Proceeds")), "###0.00")
                                        .Range(colQuickType) = "Option Assigned Stock Purchase"
                                        .Range(colMemo) = "Assignment of stock " + CStr(rowData("Underlying")) + " " + quantityPriceStr
                                    End With
                            End If
                    End Select
            End Select
        Loop
    End With
End Sub

Function Insert(original As String, added As String, pos As Long) As String

    If pos < 1 Then pos = 1
    If Len(original) < pos Then pos = Len(original) + 1

    Insert = Mid(original, 1, pos - 1) _
                        & added _
                        & Mid(original, pos, Len(original) - pos + 1)

End Function

Sub WriteStatementFile()

    ' statement dtails
    Const bankName As String = "IKBR"
    Const customerID As String = "U3396787"
    Const accountCurrency = "USD"
    Const otherAccountName = "Broker" 'other party sender/recipient of bank transactions
    Dim accountID As String: account = customerID + " " + accountCurrency ' our account name in accounting package
    
    Const ContextUndefCaseID = 1000000
    
  Dim Line As String
  Dim OutputFileNum As Integer
  Dim PathName As String

  PathName = Application.ActiveWorkbook.Path
  OutputFileNum = FreeFile

  Open PathName & "\Test.csv" For Output Lock Write As #OutputFileNum

  ' statement header
  Print #OutputFileNum, "0:bank[varchar(6)], 1:account[varchar(24)], 2:currency[varchar(4)], 3:startBalance[double], 4:endBalance[double], 5:smtDate[date], 6:number[int(11)], 7:seq[int(11)], 8:statementId[varchar(32)]"
  Print #OutputFileNum, bankName + "," + accountID + ",0.00,0.00,2022-06-30,Option,YA2022,2022-06-30:YA2022:Options"
  
  ' transaction header
  Print #OutputFileNum, "0:valueTimestamp[date],1:entryTimestamp[date],2:account[varchar(24)],3:accountName[varchar(60)],4:transactionCode[varchar(32)],5:transactionCodeDesc[varchar(32)],6:transactionDC[varchar(2)],7:transactionAmount[double],8:transactionTitle[varchar(256)],9:transactionChargeAmount[double],10:transactionChargeTitle[varchar(256)]"
  
  Set ColumnHeader = CreateObject("System.Collections.ArrayList")
    ' get column lables
    Dim col As Long
    col = 1
    
    With ActiveSheet
        Do Until IsEmpty(Cells(rowHeader, col))
            ColumnHeader.Add (Cells(rowHeader, col).Value)
            col = col + 1
        Loop
    End With

    ' loop through rows
    Dim rowData As Object 'Declare a generic Object reference
    Set rowData = CreateObject("Scripting.Dictionary") 'Late Binding of the Dictionary
    
    Dim row As Long
    row = rowFirst
    With ActiveSheet
        Do Until IsEmpty(Cells(row, 1))
            rowData.RemoveAll
            For col = 1 To ColumnHeader.Count
                rowData.Add ColumnHeader(col - 1), Cells(row, col).Value
            Next col
            row = row + 1
            
            Dim quantityPriceStr As String
            Dim avgPrice As Variant: avgPrice = CDec(0.6)
            
            Dim memoStr As String
            Dim quickEntry As String
            Dim txnID As String
            Dim indicatorDC As String
            
            Select Case rowData("Asset Category")
                Case "Equity and Index Options"
                    ' price information
                    If (rowData("Sum of Quantity") <> 0) Then
                        avgPrice = Abs(rowData("Sum of Proceeds") / (rowData("Sum of Quantity") * 100)) ' equity option is for lots of 100 stocks
                        quantityPriceStr = "#" + CStr(Abs(rowData("Sum of Quantity")))
                        quantityPriceStr = quantityPriceStr + " @" + Format(avgPrice, "###0.00")
                        quantityPriceStr = quantityPriceStr + " " + rowData("Currency")
                    End If

                    Select Case rowData("Code")
                        Case "O", "C"
                            If rowData("Sum of Proceeds") <> 0 Then
                                ' add option transaction
                                If rowData("Sum of Quantity") < 0 Then
                                    ' sell (write) option
                                    indicatorDC = "C"
                                    quickEntry = "Option Premium Received"
                                    memoStr = "Sell " + LCase(rowData("Put/Call")) + " " + rowData("Symbol") + " " + quantityPriceStr
                                ElseIf rowData("Sum of Quantity") > 0 Then
                                    ' buy back option or buy put
                                    indicatorDC = "D"
                                    quickEntry = "Option Premium Paid"
                                    memoStr = "Buy " + LCase(rowData("Put/Call")) + " " + rowData("Symbol") + " " + quantityPriceStr
                                End If
                                
                                ' 0:valueTimestamp[date],1:entryTimestamp[date],2:account[varchar(24)],
                                ' 3:accountName[varchar(60)],4:transactionCode[varchar(32)],5:transactionCodeDesc[varchar(32)],
                                ' 6:transactionDC[varchar(2)],7:transactionAmount[double],8:transactionTitle[varchar(256)],9:transactionChargeAmount[double],10:transactionChargeTitle[varchar(256)]"
                                
                                ' unique transactionID
                                txnID = rowData("Symbol") + ":" + rowData("Date") + ":PRO" ' proceeds part of tansaction
                                Print #OutputFileNum, rowData("Date") + "," + rowData("Date") + "," + accountID + "," + _
                                    otherAccountName + "," + txnID + ",Quick Entry:" + quickEntry + "," + _
                                    indicatorDC + "," + Format(Abs(rowData("Sum of Proceeds")), "###0.00") + memoStr + ",,0.00"
                            End If
                            
                            If rowData("Sum of Comm/Fee") <> 0 Then
                                ' add commission transaction
                                If rowData("Sum of Comm/Fee") < 0 Then
                                    indicatorDC = "D"
                                    quickEntry = "Option Broker Commission Paid"
                                ElseIf rowData("Sum of Comm/Fee") > 0 Then
                                    indicatorDC = "C"
                                    quickEntry = "Option Broker Commission Received"
                                End If
                                ' unique transactionID
                                txnID = rowData("Symbol") + ":" + rowData("Date") + ":COM" ' commission part of tansaction
                                Print #OutputFileNum, rowData("Date") + "," + rowData("Date") + "," + accountID + "," + _
                                    otherAccountName + "," + txnID + ",Quick Entry:" + quickEntry + "," + _
                                    indicatorDC + "," + Format(Abs(rowData("Sum of Comm/Fee")), "###0.00") + memoStr + ",,0.00"
                            End If
                        Case "A;C" ' to do
                            If (rowData("Put/Call") = "PUT") Then
                                ' put was assigned
                                quickEntry = "Option Assigned Stock Premium Transfer"
                                quickEntry = "Assignment of stock " + CStr(rowData("Underlying")) + " #" + CStr(Abs(rowData("Sum of Quantity") * 100)) + " for put " + CStr(rowData("Symbol")) + " " + CStr(Abs(rowData("Sum of Quantity")))
                            End If
                    End Select
                Case "Stocks"
                    ' price information
                    If (rowData("Sum of Quantity") <> 0) Then
                        avgPrice = Abs(rowData("Sum of Proceeds") / rowData("Sum of Quantity")) ' equity option is for lots of 100 stocks
                        quantityPriceStr = "#" + CStr(Abs(rowData("Sum of Quantity")))
                        quantityPriceStr = quantityPriceStr + " @" + Format(avgPrice, "###0.00")
                        quantityPriceStr = quantityPriceStr + " " + rowData("Currency")
                    End If
                    Select Case rowData("Code")
                        Case "A;O"
                            If rowData("Sum of Quantity") > 0 Then
                                indicatorDC = "D"
                                quickEntry = "Option Assigned Stock Purchase"
                                memoStr = "Assignment of stock " + CStr(rowData("Underlying")) + " " + quantityPriceStr
                            Else
                                Err.Raise vbObjectError + 513, "MyProj.MyObject", _
                                "Stocks A;O with Sum of Quantity < -", "", _
                                ContextUndefCaseID
                            End If
                             ' unique transactionID
                            txnID = rowData("Symbol") + ":" + rowData("Date") + ":PRO" ' proceeds part of tansaction
                            Print #OutputFileNum, rowData("Date") + "," + rowData("Date") + "," + accountID + "," + _
                                otherAccountName + "," + txnID + ",Quick Entry:" + quickEntry + "," + _
                                indicatorDC + "," + Format(Abs(rowData("Sum of Proceeds")), "###0.00") + memoStr + ",,0.00"
                                
                        Case "O", "C"
                            Err.Raise vbObjectError + 513, "MyProj.MyObject", _
                                "Stocks O or C not yet implemented", "", _
                                ContextUndefCaseID
                    End Select
            End Select
        Loop
    End With

  Close OutputFileNum

End Function

