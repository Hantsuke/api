const express = require('express');
const mysql = require('mysql2');
const bodyParser = require('body-parser');

const app = express();
const port = process.env.PORT || 3000;

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

const db = mysql.createConnection({
    host: 'monorail.proxy.rlwy.net', // Endereço do servidor MySQL
    user: 'root', // Nome de usuário do MySQL
    password: 'RFvpUdFgVNptamyhkfetrDzUyOzRDFms', // Senha do MySQL
    database: 'base_de_dados', // Nome do banco de dados
    port: 52314
});

db.connect((err) => {
    if (err) throw err;
    console.log('Conectado ao banco de dados MySQL.');
});

app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Methods', 'GET, PUT, POST, DELETE, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type, Content-Range, Content-Disposition, X-Requested-With, enctype, Content-Description');
    next();
});

const executarConsulta = (sql, unico = false) => {
    return new Promise((resolve, reject) => {
        db.query(sql, (error, results) => {
            if (error) return reject(error);
            resolve(unico ? results[0] : results);
        });
    });
};

const construirClausulaWhere = (params) => {
    const clausulasWhere = [];
    for (const chave in params) {
        if (params.hasOwnProperty(chave) && chave !== 'resource' && chave !== 'column' && chave !== 'value') {
            clausulasWhere.push(`\`${chave}\`='${params[chave]}'`);
        }
    }
    return clausulasWhere.length > 0 ? `WHERE ${clausulasWhere.join(' AND ')}` : '';
};

const tratarRequisicao = async (req, res, tabela) => {
    const metodo = req.method;
    const params = req.query;
    const dados = req.body;

    try {
        let resposta;
        switch (metodo) {
            case 'GET':
                if (params.column && params.value) {
                    const coluna = params.column;
                    const valor = params.value;
                    const sql = `SELECT * FROM \`${tabela}\` WHERE \`${coluna}\`='${valor}'`;
                    resposta = await executarConsulta(sql);
                } else {
                    const clausulaWhere = construirClausulaWhere(params);
                    const sql = `SELECT * FROM \`${tabela}\` ${clausulaWhere}`;
                    resposta = await executarConsulta(sql);
                }
                res.json(resposta);
                break;
            
            case 'PUT':
                if (params.column && params.value) {
                    const coluna = params.column;
                    const valor = params.value;
                    const camposAtualizar = Object.keys(dados).map(chave => `\`${chave}\`='${dados[chave]}'`).join(', ');
                    const sql = `UPDATE \`${tabela}\` SET ${camposAtualizar} WHERE \`${coluna}\`='${valor}'`;
                    const resultado = await executarConsulta(sql);
                    res.json({ status: 'Sucesso', updated_rows: resultado.affectedRows });
                }
                break;

            case 'POST':
                const colunas = Object.keys(dados).map(coluna => `\`${coluna}\``).join(', ');
                const valores = Object.values(dados).map(valor => `'${valor}'`).join(', ');
                const sql = `INSERT INTO \`${tabela}\` (${colunas}) VALUES (${valores})`;
                const resultado = await executarConsulta(sql);
                res.json({ ID: resultado.insertId, ...dados });
                break;

            case 'DELETE':
                if (params.column && params.value) {
                    const coluna = params.column;
                    const valor = params.value;
                    const sql = `DELETE FROM \`${tabela}\` WHERE \`${coluna}\`='${valor}'`;
                    const resultado = await executarConsulta(sql);
                    res.json({ status: 'Sucesso', deleted_rows: resultado.affectedRows });
                }
                break;

            default:
                res.status(405).json({ status: 'Método não suportado' });
        }
    } catch (error) {
        res.status(500).json({ status: 'Erro no servidor', erro: error.message });
    }
};

const recursos = ['categorias', 'usuarios', 'transacao', 'planejamento_mensal'];

recursos.forEach(recurso => {
    app.route(`/${recurso}`)
        .get((req, res) => tratarRequisicao(req, res, recurso))
        .post((req, res) => tratarRequisicao(req, res, recurso))
        .put((req, res) => tratarRequisicao(req, res, recurso))
        .delete((req, res) => tratarRequisicao(req, res, recurso));
});

app.listen(port, () => {
    console.log(`Servidor rodando na porta ${port}`);
});
