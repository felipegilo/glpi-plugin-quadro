<?php

namespace GlpiPlugin\Quadros;

use Session;

/**
 * Logica de todas as acoes do quadro (listas, cartoes, membros).
 * Usada pelo endpoint ajax/quadro.php; separada para poder ser testada por CLI.
 */
class Api
{
    public const CORES = ['green', 'yellow', 'orange', 'red', 'purple', 'blue'];

    /** @return array{ok:bool, data?:mixed, error?:string} */
    public static function handle(array $post): array
    {
        try {
            return ['ok' => true, 'data' => self::dispatch($post)];
        } catch (ApiException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private static function dispatch(array $p): mixed
    {
        $action = (string) ($p['action'] ?? '');
        $qid    = (int) ($p['quadros_id'] ?? 0);

        switch ($action) {
            case 'board':
                self::board($qid, READ);
                return self::boardData($qid);
            case 'usuarios':
                self::board($qid, READ);
                return self::usuarios();

            case 'lista_add':
                self::board($qid, UPDATE);
                return self::listaAdd($qid, self::nome($p['name'] ?? ''));
            case 'lista_rename':
                self::board($qid, UPDATE);
                return self::listaRename(self::lista($qid, (int) ($p['id'] ?? 0)), self::nome($p['name'] ?? ''));
            case 'lista_delete':
                self::board($qid, UPDATE);
                return self::listaDelete(self::lista($qid, (int) ($p['id'] ?? 0)));
            case 'lista_move':
                self::board($qid, UPDATE);
                return self::listaMove($qid, self::ids($p['ordem'] ?? []));

            case 'cartao_add':
                self::board($qid, UPDATE);
                $lista = self::lista($qid, (int) ($p['lista_id'] ?? 0));
                return self::cartaoAdd($qid, $lista, self::nome($p['name'] ?? ''));
            case 'cartao_update':
                self::board($qid, UPDATE);
                return self::cartaoUpdate(self::cartao($qid, (int) ($p['id'] ?? 0)), $p);
            case 'cartao_delete':
                self::board($qid, UPDATE);
                return self::cartaoDelete(self::cartao($qid, (int) ($p['id'] ?? 0)));
            case 'cartao_move':
                self::board($qid, UPDATE);
                $cartao = self::cartao($qid, (int) ($p['id'] ?? 0));
                $lista  = self::lista($qid, (int) ($p['lista_id'] ?? 0));
                return self::cartaoMove($qid, $cartao, $lista, self::ids($p['ordem'] ?? []));

            case 'cartao_detalhes':
                self::board($qid, READ);
                return self::detalhes(self::cartao($qid, (int) ($p['id'] ?? 0)));

            case 'item_add':
                self::board($qid, UPDATE);
                return self::itemAdd(self::cartao($qid, (int) ($p['cartao_id'] ?? 0)), self::nome($p['name'] ?? ''));
            case 'item_toggle':
                self::board($qid, UPDATE);
                return self::itemToggle($qid, (int) ($p['id'] ?? 0), (int) (bool) ($p['is_done'] ?? 0));
            case 'item_delete':
                self::board($qid, UPDATE);
                return self::itemDelete($qid, (int) ($p['id'] ?? 0));

            case 'comentario_add':
                self::board($qid, UPDATE);
                return self::comentarioAdd(self::cartao($qid, (int) ($p['cartao_id'] ?? 0)), (string) ($p['content'] ?? ''));
            case 'comentario_delete':
                self::board($qid, READ);
                return self::comentarioDelete($qid, (int) ($p['id'] ?? 0));
        }
        throw new ApiException('Ação inválida.');
    }

    // ---------------------------------------------------------------- helpers

    private static function board(int $qid, int $right): Quadro
    {
        $board = new Quadro();
        if ($qid <= 0 || !$board->getFromDB($qid)) {
            throw new ApiException('Quadro não encontrado.');
        }
        if (!$board->can($qid, $right)) {
            throw new ApiException('Você não tem permissão para esta ação.');
        }
        return $board;
    }

    private static function nome(mixed $v): string
    {
        $v = trim((string) $v);
        if ($v === '') {
            throw new ApiException('Informe um nome.');
        }
        return mb_substr($v, 0, 255);
    }

    /** @return int[] */
    private static function ids(mixed $v): array
    {
        if (!is_array($v)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $v), fn($i) => $i > 0)));
    }

    private static function lista(int $qid, int $id): Lista
    {
        $l = new Lista();
        if ($id <= 0 || !$l->getFromDB($id) || (int) $l->fields['plugin_quadros_quadros_id'] !== $qid) {
            throw new ApiException('Lista não encontrada.');
        }
        return $l;
    }

    private static function cartao(int $qid, int $id): Cartao
    {
        $c = new Cartao();
        if ($id <= 0 || !$c->getFromDB($id) || (int) $c->fields['plugin_quadros_quadros_id'] !== $qid) {
            throw new ApiException('Cartão não encontrado.');
        }
        return $c;
    }

    private static function nextPosition(string $table, array $where): int
    {
        /** @var \DBmysql $DB */
        global $DB;
        $row = $DB->request([
            'SELECT' => ['MAX' => 'position AS maxpos'],
            'FROM'   => $table,
            'WHERE'  => $where,
        ])->current();
        return (int) ($row['maxpos'] ?? 0) + 1;
    }

    private static function userLabel(array $u): array
    {
        $label = trim(($u['firstname'] ?? '') . ' ' . ($u['realname'] ?? ''));
        if ($label === '') {
            $label = (string) $u['name'];
        }
        $words    = preg_split('/\s+/u', $label) ?: [$label];
        $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . (count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
        return ['id' => (int) $u['id'], 'label' => $label, 'initials' => $initials];
    }

    // ------------------------------------------------------------------ leitura

    private static function boardData(int $qid): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $board = new Quadro();
        $board->getFromDB($qid);

        $listas = [];
        foreach ($DB->request([
            'FROM'  => Lista::getTable(),
            'WHERE' => ['plugin_quadros_quadros_id' => $qid],
            'ORDER' => ['position', 'id'],
        ]) as $l) {
            $listas[(int) $l['id']] = ['id' => (int) $l['id'], 'name' => $l['name'], 'cartoes' => []];
        }

        $cartoes = [];
        foreach ($DB->request([
            'FROM'  => Cartao::getTable(),
            'WHERE' => ['plugin_quadros_quadros_id' => $qid],
            'ORDER' => ['position', 'id'],
        ]) as $c) {
            $cartoes[(int) $c['id']] = [
                'id'          => (int) $c['id'],
                'name'        => $c['name'],
                'description' => (string) $c['description'],
                'date_due'    => $c['date_due'],
                'cor'         => (string) $c['cor'],
                'is_done'     => (int) $c['is_done'],
                'members'     => [],
                '_lista'      => (int) $c['plugin_quadros_listas_id'],
            ];
        }

        if ($cartoes) {
            foreach ($DB->request([
                'SELECT'    => ['m.plugin_quadros_cartoes_id', 'u.id', 'u.name', 'u.firstname', 'u.realname'],
                'FROM'      => Cartao::TABLE_MEMBROS . ' AS m',
                'INNER JOIN' => ['glpi_users AS u' => ['ON' => ['m' => 'users_id', 'u' => 'id']]],
                'WHERE'     => ['m.plugin_quadros_cartoes_id' => array_keys($cartoes)],
                'ORDER'     => ['m.id'],
            ]) as $m) {
                $cartoes[(int) $m['plugin_quadros_cartoes_id']]['members'][] = self::userLabel($m);
            }
        }

        $contagens = $cartoes ? self::contagensVarios(array_keys($cartoes)) : [];

        foreach ($cartoes as $c) {
            $lid = $c['_lista'];
            unset($c['_lista']);
            $c += $contagens[$c['id']] ?? ['check_total' => 0, 'check_done' => 0, 'comentarios' => 0];
            if (isset($listas[$lid])) {
                $listas[$lid]['cartoes'][] = $c;
            }
        }

        return [
            'board'    => [
                'id'      => $qid,
                'name'    => $board->fields['name'],
                'comment' => (string) $board->fields['comment'],
            ],
            'can_edit' => (bool) $board->can($qid, UPDATE),
            'listas'   => array_values($listas),
        ];
    }

    private static function usuarios(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $out = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'firstname', 'realname'],
            'FROM'   => 'glpi_users',
            'WHERE'  => ['is_active' => 1, 'is_deleted' => 0],
            'ORDER'  => ['realname', 'firstname', 'name'],
            'LIMIT'  => 1000,
        ]) as $u) {
            $out[] = self::userLabel($u);
        }
        return $out;
    }

    // ------------------------------------------------------------------ listas

    private static function listaAdd(int $qid, string $nome): array
    {
        $l  = new Lista();
        $id = $l->add([
            'plugin_quadros_quadros_id' => $qid,
            'name'                      => $nome,
            'position'                  => self::nextPosition(Lista::getTable(), ['plugin_quadros_quadros_id' => $qid]),
        ]);
        if (!$id) {
            throw new ApiException('Não foi possível criar a lista.');
        }
        return ['id' => (int) $id, 'name' => $nome, 'cartoes' => []];
    }

    private static function listaRename(Lista $l, string $nome): array
    {
        $l->update(['id' => $l->getID(), 'name' => $nome]);
        return ['id' => (int) $l->getID(), 'name' => $nome];
    }

    private static function listaDelete(Lista $l): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $ids = array_column(iterator_to_array($DB->request([
            'SELECT' => ['id'],
            'FROM'   => Cartao::getTable(),
            'WHERE'  => ['plugin_quadros_listas_id' => $l->getID()],
        ])), 'id');
        foreach ($ids as $cid) {
            $c = new Cartao();
            $c->delete(['id' => $cid], true);
        }
        $l->delete(['id' => $l->getID()], true);
        return ['id' => (int) $l->getID(), 'cartoes_removidos' => count($ids)];
    }

    private static function listaMove(int $qid, array $ordem): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $validos = array_column(iterator_to_array($DB->request([
            'SELECT' => ['id'],
            'FROM'   => Lista::getTable(),
            'WHERE'  => ['plugin_quadros_quadros_id' => $qid],
        ])), 'id');
        foreach ($ordem as $pos => $id) {
            if (in_array($id, array_map('intval', $validos), true)) {
                $DB->update(Lista::getTable(), ['position' => $pos + 1], ['id' => $id]);
            }
        }
        return ['ordem' => $ordem];
    }

    // ----------------------------------------------------------------- cartoes

    private static function cartaoAdd(int $qid, Lista $lista, string $nome): array
    {
        $c  = new Cartao();
        $id = $c->add([
            'plugin_quadros_quadros_id' => $qid,
            'plugin_quadros_listas_id'  => $lista->getID(),
            'name'                      => $nome,
            'users_id'                  => (int) Session::getLoginUserID(),
            'position'                  => self::nextPosition(Cartao::getTable(), ['plugin_quadros_listas_id' => $lista->getID()]),
        ]);
        if (!$id) {
            throw new ApiException('Não foi possível criar o cartão.');
        }
        return [
            'id' => (int) $id, 'name' => $nome, 'description' => '', 'date_due' => null,
            'cor' => '', 'is_done' => 0, 'members' => [],
            'check_total' => 0, 'check_done' => 0, 'comentarios' => 0,
        ];
    }

    private static function cartaoUpdate(Cartao $c, array $p): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $input = ['id' => $c->getID()];
        if (array_key_exists('name', $p)) {
            $input['name'] = self::nome($p['name']);
        }
        if (array_key_exists('description', $p)) {
            $input['description'] = (string) $p['description'];
        }
        if (array_key_exists('cor', $p)) {
            $cor = (string) $p['cor'];
            if ($cor !== '' && !in_array($cor, self::CORES, true)) {
                throw new ApiException('Cor inválida.');
            }
            $input['cor'] = $cor;
        }
        if (array_key_exists('is_done', $p)) {
            $input['is_done'] = (int) (bool) $p['is_done'];
        }
        $c->update($input);

        if (array_key_exists('date_due', $p)) {
            $d = trim((string) $p['date_due']);
            if ($d === '') {
                $DB->update(Cartao::getTable(), ['date_due' => null], ['id' => $c->getID()]);
            } else {
                $ok = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
                if (!$ok) {
                    throw new ApiException('Data inválida.');
                }
                $DB->update(Cartao::getTable(), ['date_due' => $d], ['id' => $c->getID()]);
            }
        }

        if (array_key_exists('members', $p) || array_key_exists('members_set', $p)) {
            $ids = self::ids($p['members'] ?? []);
            if ($ids) {
                $ids = array_map('intval', array_column(iterator_to_array($DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => 'glpi_users',
                    'WHERE'  => ['id' => $ids, 'is_deleted' => 0],
                ])), 'id'));
            }
            $DB->delete(Cartao::TABLE_MEMBROS, ['plugin_quadros_cartoes_id' => $c->getID()]);
            foreach ($ids as $uid) {
                $DB->insert(Cartao::TABLE_MEMBROS, ['plugin_quadros_cartoes_id' => $c->getID(), 'users_id' => $uid]);
            }
        }

        $c->getFromDB($c->getID());
        $membros = [];
        foreach ($DB->request([
            'SELECT'     => ['u.id', 'u.name', 'u.firstname', 'u.realname'],
            'FROM'       => Cartao::TABLE_MEMBROS . ' AS m',
            'INNER JOIN' => ['glpi_users AS u' => ['ON' => ['m' => 'users_id', 'u' => 'id']]],
            'WHERE'      => ['m.plugin_quadros_cartoes_id' => $c->getID()],
            'ORDER'      => ['m.id'],
        ]) as $u) {
            $membros[] = self::userLabel($u);
        }
        return [
            'id'          => (int) $c->getID(),
            'name'        => $c->fields['name'],
            'description' => (string) $c->fields['description'],
            'date_due'    => $c->fields['date_due'],
            'cor'         => (string) $c->fields['cor'],
            'is_done'     => (int) $c->fields['is_done'],
            'members'     => $membros,
        ] + self::contagens((int) $c->getID());
    }

    private static function cartaoDelete(Cartao $c): array
    {
        $id = (int) $c->getID();
        $c->delete(['id' => $id], true);
        return ['id' => $id];
    }

    // ------------------------------------------------- checklist e comentarios

    /** @return array<int, array{check_total:int, check_done:int, comentarios:int}> */
    private static function contagensVarios(array $cartao_ids): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $out = [];
        foreach ($cartao_ids as $id) {
            $out[(int) $id] = ['check_total' => 0, 'check_done' => 0, 'comentarios' => 0];
        }
        foreach ($DB->request([
            'SELECT'  => ['plugin_quadros_cartoes_id', 'COUNT' => 'id AS total', 'SUM' => 'is_done AS feitos'],
            'FROM'    => Cartao::TABLE_CHECKLIST,
            'WHERE'   => ['plugin_quadros_cartoes_id' => $cartao_ids],
            'GROUPBY' => 'plugin_quadros_cartoes_id',
        ]) as $r) {
            $out[(int) $r['plugin_quadros_cartoes_id']]['check_total'] = (int) $r['total'];
            $out[(int) $r['plugin_quadros_cartoes_id']]['check_done']  = (int) $r['feitos'];
        }
        foreach ($DB->request([
            'SELECT'  => ['plugin_quadros_cartoes_id', 'COUNT' => 'id AS total'],
            'FROM'    => Cartao::TABLE_COMENTARIOS,
            'WHERE'   => ['plugin_quadros_cartoes_id' => $cartao_ids],
            'GROUPBY' => 'plugin_quadros_cartoes_id',
        ]) as $r) {
            $out[(int) $r['plugin_quadros_cartoes_id']]['comentarios'] = (int) $r['total'];
        }
        return $out;
    }

    private static function contagens(int $cartao_id): array
    {
        return self::contagensVarios([$cartao_id])[$cartao_id];
    }

    private static function itemRow(array $r): array
    {
        return ['id' => (int) $r['id'], 'name' => $r['name'], 'is_done' => (int) $r['is_done']];
    }

    private static function comentarioRow(array $r): array
    {
        $autor = self::userLabel($r);
        return [
            'id'      => (int) $r['id'],
            'content' => (string) $r['content'],
            'date'    => $r['date_creation'],
            'author'  => ['id' => (int) $r['users_id'], 'label' => $autor['label'], 'initials' => $autor['initials']],
            'can_delete' => self::podeApagarComentario((int) $r['users_id']),
        ];
    }

    private static function podeApagarComentario(int $autor_id): bool
    {
        return ($autor_id > 0 && $autor_id === (int) Session::getLoginUserID())
            || Session::haveRight(Quadro::$rightname, PURGE);
    }

    private static function detalhes(Cartao $c): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $itens = [];
        foreach ($DB->request([
            'FROM'  => Cartao::TABLE_CHECKLIST,
            'WHERE' => ['plugin_quadros_cartoes_id' => $c->getID()],
            'ORDER' => ['position', 'id'],
        ]) as $r) {
            $itens[] = self::itemRow($r);
        }

        $coment = [];
        foreach ($DB->request([
            'SELECT'     => ['m.id', 'm.content', 'm.date_creation', 'm.users_id', 'u.name', 'u.firstname', 'u.realname'],
            'FROM'       => Cartao::TABLE_COMENTARIOS . ' AS m',
            'LEFT JOIN'  => ['glpi_users AS u' => ['ON' => ['m' => 'users_id', 'u' => 'id']]],
            'WHERE'      => ['m.plugin_quadros_cartoes_id' => $c->getID()],
            'ORDER'      => ['m.date_creation DESC', 'm.id DESC'],
        ]) as $r) {
            $r['name'] = $r['name'] ?? 'usuário removido';
            $coment[] = self::comentarioRow($r + ['firstname' => $r['firstname'] ?? '', 'realname' => $r['realname'] ?? '']);
        }
        return ['checklist' => $itens, 'comentarios' => $coment] + self::contagens((int) $c->getID());
    }

    private static function itemDoQuadro(int $qid, int $id): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $r = $DB->request(['FROM' => Cartao::TABLE_CHECKLIST, 'WHERE' => ['id' => $id]])->current();
        if (!$r) {
            throw new ApiException('Item não encontrado.');
        }
        self::cartao($qid, (int) $r['plugin_quadros_cartoes_id']); // garante que pertence a este quadro
        return $r;
    }

    private static function itemAdd(Cartao $c, string $nome): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $DB->insert(Cartao::TABLE_CHECKLIST, [
            'plugin_quadros_cartoes_id' => $c->getID(),
            'name'                      => $nome,
            'is_done'                   => 0,
            'position'                  => self::nextPosition(Cartao::TABLE_CHECKLIST, ['plugin_quadros_cartoes_id' => $c->getID()]),
        ]);
        $id = (int) $DB->insertId();
        return ['item' => ['id' => $id, 'name' => $nome, 'is_done' => 0]] + self::contagens((int) $c->getID());
    }

    private static function itemToggle(int $qid, int $id, int $feito): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $r = self::itemDoQuadro($qid, $id);
        $DB->update(Cartao::TABLE_CHECKLIST, ['is_done' => $feito], ['id' => $id]);
        $r['is_done'] = $feito;
        return ['item' => self::itemRow($r)] + self::contagens((int) $r['plugin_quadros_cartoes_id']);
    }

    private static function itemDelete(int $qid, int $id): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $r = self::itemDoQuadro($qid, $id);
        $DB->delete(Cartao::TABLE_CHECKLIST, ['id' => $id]);
        return ['id' => $id] + self::contagens((int) $r['plugin_quadros_cartoes_id']);
    }

    private static function comentarioAdd(Cartao $c, string $texto): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $texto = trim($texto);
        if ($texto === '') {
            throw new ApiException('Escreva o comentário.');
        }
        $texto = mb_substr($texto, 0, 5000);
        $uid   = (int) Session::getLoginUserID();
        $agora = date('Y-m-d H:i:s');
        $DB->insert(Cartao::TABLE_COMENTARIOS, [
            'plugin_quadros_cartoes_id' => $c->getID(),
            'users_id'                  => $uid,
            'content'                   => $texto,
            'date_creation'             => $agora,
        ]);
        $id = (int) $DB->insertId();
        $u  = $DB->request(['SELECT' => ['name', 'firstname', 'realname'], 'FROM' => 'glpi_users', 'WHERE' => ['id' => $uid]])->current()
            ?: ['name' => 'usuário', 'firstname' => '', 'realname' => ''];
        $row = self::comentarioRow([
            'id' => $id, 'content' => $texto, 'date_creation' => $agora, 'users_id' => $uid,
        ] + $u);
        return ['comentario' => $row] + self::contagens((int) $c->getID());
    }

    private static function comentarioDelete(int $qid, int $id): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $r = $DB->request(['FROM' => Cartao::TABLE_COMENTARIOS, 'WHERE' => ['id' => $id]])->current();
        if (!$r) {
            throw new ApiException('Comentário não encontrado.');
        }
        self::cartao($qid, (int) $r['plugin_quadros_cartoes_id']);
        if (!self::podeApagarComentario((int) $r['users_id'])) {
            throw new ApiException('Você só pode excluir os seus próprios comentários.');
        }
        $DB->delete(Cartao::TABLE_COMENTARIOS, ['id' => $id]);
        return ['id' => $id] + self::contagens((int) $r['plugin_quadros_cartoes_id']);
    }

    private static function cartaoMove(int $qid, Cartao $c, Lista $destino, array $ordem): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        // O cartao movido sempre entra na ordem final, mesmo que o cliente nao o envie
        if (!in_array((int) $c->getID(), $ordem, true)) {
            $ordem[] = (int) $c->getID();
        }

        $DB->update(Cartao::getTable(), ['plugin_quadros_listas_id' => $destino->getID()], ['id' => $c->getID()]);

        // Apenas cartoes que pertencem a este quadro tem a posicao atualizada
        $validos = array_map('intval', array_column(iterator_to_array($DB->request([
            'SELECT' => ['id'],
            'FROM'   => Cartao::getTable(),
            'WHERE'  => ['plugin_quadros_quadros_id' => $qid],
        ])), 'id'));
        foreach ($ordem as $pos => $id) {
            if (in_array($id, $validos, true)) {
                $DB->update(Cartao::getTable(), ['position' => $pos + 1], ['id' => $id]);
            }
        }
        return ['id' => (int) $c->getID(), 'lista_id' => (int) $destino->getID()];
    }
}
