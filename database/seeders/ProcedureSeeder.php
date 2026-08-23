<?php

namespace Database\Seeders;

use App\Models\Procedure;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Catálogo inicial de Procedimentos de Enfermagem.
 *
 * Os textos abaixo são conteúdo didático de demonstração, escritos para o
 * seed; cada instituição (tenant) recebe sua própria cópia editável, então
 * o catálogo pode ser adaptado aos POPs locais sem afetar os demais tenants.
 */
class ProcedureSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            return;
        }

        foreach ($tenants as $tenant) {
            $order = 1;

            foreach ($this->procedures() as $data) {
                $exists = Procedure::withoutGlobalScope('tenant')
                    ->withTrashed()
                    ->where('tenant_id', $tenant->id)
                    ->where('slug', $data['slug'])
                    ->exists();

                if ($exists) {
                    $order++;

                    continue;
                }

                Procedure::create([
                    'tenant_id' => $tenant->id,
                    'title' => $data['title'],
                    'slug' => $data['slug'],
                    'category' => $data['category'],
                    'short_description' => $data['short_description'],
                    'content' => $this->buildContent($data),
                    'order' => $order++,
                    'status' => Procedure::STATUS_PUBLISHED,
                    'published_at' => now(),
                    'meta_title' => $data['title'].' | Posto de Enfermagem',
                    'meta_description' => $data['short_description'],
                ]);
            }
        }
    }

    /**
     * Monta o HTML do procedimento a partir das seções estruturadas.
     *
     * @param  array<string, mixed>  $data
     */
    private function buildContent(array $data): string
    {
        $html = '<h2>Definição e objetivo</h2>';
        $html .= '<p>'.$data['objetivo'].'</p>';

        $html .= '<h2>Material necessário</h2><ul>';
        foreach ($data['materiais'] as $item) {
            $html .= '<li>'.$item.'</li>';
        }
        $html .= '</ul>';

        $html .= '<h2>Técnica passo a passo</h2><ol>';
        foreach ($data['tecnica'] as $passo) {
            $html .= '<li>'.$passo.'</li>';
        }
        $html .= '</ol>';

        $html .= '<h2>Cuidados de enfermagem e pontos de atenção</h2><ul>';
        foreach ($data['cuidados'] as $cuidado) {
            $html .= '<li>'.$cuidado.'</li>';
        }
        $html .= '</ul>';

        $html .= '<h2>Registro</h2>';
        $html .= '<p>'.$data['registro'].'</p>';

        return $html;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function procedures(): array
    {
        return [
            // ==========================================
            // 1. APLICAÇÃO DE MEDICAMENTOS
            // ==========================================
            [
                'title' => 'Administração de Medicamentos por Via Intramuscular',
                'slug' => 'administracao-de-medicamentos-por-via-intramuscular',
                'category' => Procedure::CATEGORY_APLICACAO_MEDICAMENTOS,
                'short_description' => 'Técnica de aplicação intramuscular, escolha do sítio (ventroglúteo, deltoide, vasto lateral) e volumes máximos por região.',
                'objetivo' => 'Introduzir o medicamento no tecido muscular profundo, que por ser bem vascularizado permite absorção mais rápida que a via subcutânea e comporta volumes maiores. Indicada para fármacos irritantes ao subcutâneo, soluções oleosas e vacinas.',
                'materiais' => [
                    'Bandeja limpa e prescrição médica conferida',
                    'Medicamento prescrito e diluente, quando necessário',
                    'Seringa de 3 a 5 mL e agulhas (uma para aspirar, outra para aplicar: 25x7, 25x8 ou 30x8 conforme massa muscular)',
                    'Algodão e álcool a 70%',
                    'Luvas de procedimento e recipiente para perfurocortantes',
                ],
                'tecnica' => [
                    'Conferir os nove certos da administração de medicamentos (paciente, medicamento, dose, via, hora, registro, orientação, forma farmacêutica e resposta).',
                    'Higienizar as mãos, reunir o material e explicar o procedimento ao paciente.',
                    'Preparar o medicamento em ambiente limpo, trocando a agulha após a aspiração para evitar irritação do trajeto.',
                    'Escolher o sítio: ventroglúteo (até 4 mL, região de eleição em adultos), vasto lateral da coxa (até 4 mL, eleição em lactentes), deltoide (até 2 mL) ou dorsoglúteo (evitar pelo risco de lesão do nervo isquiático).',
                    'Posicionar o paciente de modo a relaxar a musculatura e delimitar o sítio por referências anatômicas.',
                    'Fazer a antissepsia da pele com álcool a 70% em movimento único, do centro para a periferia, e aguardar secar.',
                    'Introduzir a agulha em ângulo de 90 graus, com movimento firme e rápido.',
                    'Aspirar levemente: havendo retorno sanguíneo, retirar a agulha, desprezar o material e reiniciar o preparo em outro sítio.',
                    'Injetar a solução lentamente (cerca de 1 mL a cada 10 segundos), observando a reação do paciente.',
                    'Retirar a agulha no mesmo ângulo, comprimir levemente com algodão seco, sem massagear soluções de depósito.',
                    'Desprezar o perfurocortante sem reencapar, retirar as luvas e higienizar as mãos.',
                ],
                'cuidados' => [
                    'Fazer rodízio de sítios em terapias prolongadas para prevenir fibrose e abscesso.',
                    'Não aplicar em áreas com hematoma, edema, lesão de pele, endurecimento ou paresia.',
                    'Manter o paciente em observação por no mínimo 15 a 30 minutos após imunobiológicos e fármacos com risco de anafilaxia.',
                    'Nunca reencapar agulhas: o descarte imediato em recipiente rígido é a principal medida de prevenção de acidente com perfurocortante.',
                ],
                'registro' => 'Registrar no prontuário o medicamento, a dose, a via, o sítio utilizado, o horário, as intercorrências e a resposta do paciente, com identificação e número do conselho do profissional.',
            ],
            [
                'title' => 'Administração de Medicamentos por Via Subcutânea',
                'slug' => 'administracao-de-medicamentos-por-via-subcutanea',
                'category' => Procedure::CATEGORY_APLICACAO_MEDICAMENTOS,
                'short_description' => 'Aplicação no tecido subcutâneo para insulinas, heparinas e anticoagulantes, com rodízio de sítios e ângulos de punção.',
                'objetivo' => 'Depositar o medicamento na camada de tecido adiposo abaixo da derme, onde a absorção é lenta e gradual. É a via de escolha para insulinas, heparinas de baixo peso molecular e alguns imunobiológicos, com volume habitual de até 1 mL.',
                'materiais' => [
                    'Prescrição conferida e medicamento na apresentação correta',
                    'Seringa de 1 mL (ou caneta aplicadora) com agulha curta (13x4,5, 13x3,8 ou 8 mm)',
                    'Algodão e álcool a 70%',
                    'Luvas de procedimento e recipiente para perfurocortantes',
                ],
                'tecnica' => [
                    'Conferir a prescrição e os certos da administração; higienizar as mãos.',
                    'Selecionar o sítio: face externa do braço, face anterior e lateral da coxa, região abdominal (respeitando 3 a 5 cm ao redor da cicatriz umbilical) ou região escapular.',
                    'Verificar o rodízio registrado e escolher local diferente da última aplicação.',
                    'Realizar a antissepsia com álcool a 70% e aguardar a secagem completa.',
                    'Fazer prega cutânea com o polegar e o indicador, sem comprimir o tecido.',
                    'Introduzir a agulha a 90 graus (agulhas curtas) ou a 45 graus (pacientes magros ou agulhas mais longas).',
                    'Injetar a solução lentamente; em heparinas não aspirar antes de injetar.',
                    'Aguardar cerca de 5 segundos antes de retirar a agulha, soltar a prega e comprimir suavemente sem massagear.',
                    'Descartar o perfurocortante, retirar as luvas e higienizar as mãos.',
                ],
                'cuidados' => [
                    'Massagear o local após heparina ou insulina altera a absorção e favorece hematoma — está contraindicado.',
                    'Manter mapa de rodízio para prevenir lipodistrofia, que compromete a absorção da insulina.',
                    'Conferir tipo e concentração da insulina, além da graduação da seringa correspondente, antes de aspirar.',
                    'Avaliar sinais de sangramento em pacientes anticoagulados e comunicar equimoses extensas.',
                ],
                'registro' => 'Registrar medicamento, dose, sítio aplicado, horário e queixas do paciente, mantendo atualizado o mapa de rodízio de sítios.',
            ],
            [
                'title' => 'Punção Venosa Periférica e Administração Endovenosa',
                'slug' => 'puncao-venosa-periferica-e-administracao-endovenosa',
                'category' => Procedure::CATEGORY_APLICACAO_MEDICAMENTOS,
                'short_description' => 'Instalação de cateter venoso periférico, administração de medicamentos endovenosos e prevenção de flebite e infiltração.',
                'objetivo' => 'Obter acesso vascular periférico para infusão de soluções, hemocomponentes e medicamentos que exigem início de ação imediato e biodisponibilidade integral.',
                'materiais' => [
                    'Cateter intravenoso periférico do menor calibre que atenda à terapia',
                    'Garrote, luvas de procedimento, gaze e clorexidina alcoólica a 0,5% ou álcool a 70%',
                    'Cobertura transparente estéril e etiqueta de identificação do acesso',
                    'Seringa com solução fisiológica a 0,9% para flush, equipo e suporte de soro',
                    'Recipiente para perfurocortantes',
                ],
                'tecnica' => [
                    'Explicar o procedimento, higienizar as mãos e calçar luvas de procedimento.',
                    'Selecionar preferencialmente veias do antebraço; evitar áreas de flexão, membro com fístula, esvaziamento ganglionar ou déficit motor.',
                    'Aplicar o garrote de 10 a 15 cm acima do sítio escolhido, sem ultrapassar 2 minutos.',
                    'Realizar a antissepsia com movimentos únicos e aguardar a secagem espontânea, sem palpar novamente o sítio preparado.',
                    'Tracionar a pele, puncionar com o bisel voltado para cima em ângulo de 15 a 30 graus e observar o refluxo sanguíneo.',
                    'Progredir o cateter, retirar a agulha guia e soltar o garrote.',
                    'Conectar o equipo ou realizar o flush com solução fisiológica, confirmando a permeabilidade sem dor, resistência ou edema.',
                    'Fixar com cobertura transparente estéril e identificar com data, hora, calibre e profissional responsável.',
                    'Administrar o medicamento na velocidade prescrita, observando reações imediatas.',
                ],
                'cuidados' => [
                    'Avaliar o sítio a cada plantão buscando sinais de flebite (dor, hiperemia, cordão fibroso) e infiltração (edema, palidez, redução da temperatura local).',
                    'Retirar o cateter imediatamente ao primeiro sinal de complicação e registrar o motivo.',
                    'Realizar flush antes e depois de cada medicamento para evitar incompatibilidade entre fármacos.',
                    'Trocar coberturas sujas, úmidas ou soltas assim que identificadas.',
                ],
                'registro' => 'Registrar data, hora, sítio, calibre do cateter, número de tentativas, soluções administradas e a avaliação do acesso a cada turno.',
            ],
            [
                'title' => 'Administração de Medicamentos por Via Oral e Sublingual',
                'slug' => 'administracao-de-medicamentos-por-via-oral-e-sublingual',
                'category' => Procedure::CATEGORY_APLICACAO_MEDICAMENTOS,
                'short_description' => 'Preparo e oferta segura de medicamentos orais e sublinguais, incluindo pacientes com disfagia e risco de broncoaspiração.',
                'objetivo' => 'Administrar o medicamento pelo trato gastrointestinal (via oral) ou pela mucosa sublingual, esta última com absorção rápida e sem primeira passagem hepática, indicada em situações como crise anginosa e hipertensiva.',
                'materiais' => [
                    'Prescrição conferida e medicamento na dose correta',
                    'Copo descartável, água ou espessante conforme avaliação de deglutição',
                    'Luvas de procedimento quando houver contato com mucosa',
                    'Almofariz ou triturador, apenas para apresentações que permitam trituração',
                ],
                'tecnica' => [
                    'Conferir os certos da administração e checar alergias registradas.',
                    'Higienizar as mãos e preparar o medicamento imediatamente antes da oferta, sem deixá-lo desassistido.',
                    'Posicionar o paciente sentado ou com a cabeceira elevada a pelo menos 45 graus.',
                    'Na via oral, ofertar o medicamento com água suficiente para a deglutição completa.',
                    'Na via sublingual, orientar o paciente a manter o comprimido sob a língua até a dissolução total, sem engolir, mastigar ou beber água.',
                    'Permanecer ao lado do paciente até confirmar a deglutição ou a dissolução.',
                    'Manter a cabeceira elevada por cerca de 30 minutos em pacientes com risco de broncoaspiração.',
                ],
                'cuidados' => [
                    'Nunca triturar comprimidos de liberação prolongada, revestimento entérico ou cápsulas gelatinosas sem validação do farmacêutico.',
                    'Suspender a administração e comunicar a equipe diante de disfagia, náusea intensa, rebaixamento de consciência ou jejum para exame.',
                    'Respeitar interações com alimentos: fármacos que exigem jejum ou que não podem ser tomados com laticínios devem ter horário ajustado.',
                    'Checar a prescrição somente após a administração efetiva, jamais antes.',
                ],
                'registro' => 'Registrar horário, medicamento, dose, aceitação do paciente e qualquer recusa ou intercorrência, comunicando a equipe médica quando houver dose não administrada.',
            ],

            // ==========================================
            // 2. CURATIVOS E TRATAMENTO DE FERIDAS
            // ==========================================
            [
                'title' => 'Curativo Simples com Técnica Asséptica',
                'slug' => 'curativo-simples-com-tecnica-asseptica',
                'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
                'short_description' => 'Limpeza e cobertura de feridas limpas ou com pouco exsudato, seguindo a técnica asséptica e a avaliação sistemática da lesão.',
                'objetivo' => 'Proteger a ferida de contaminação, remover exsudato e tecido inviável e manter o meio úmido favorável à cicatrização, avaliando a evolução da lesão a cada troca.',
                'materiais' => [
                    'Pacote de curativo estéril (pinças anatômica, dente de rato e Kelly) ou kit descartável',
                    'Gaze estéril, solução fisiológica a 0,9% morna e cobertura indicada para a fase da ferida',
                    'Luvas de procedimento e luvas estéreis, máscara e óculos de proteção quando houver risco de respingo',
                    'Fita adesiva hipoalergênica ou atadura, saco para resíduo infectante',
                ],
                'tecnica' => [
                    'Avaliar a prescrição de curativo e reunir o material na bancada limpa.',
                    'Explicar o procedimento, garantir privacidade e avaliar a necessidade de analgesia prévia.',
                    'Higienizar as mãos, calçar luvas de procedimento e remover a cobertura anterior, umedecendo com solução fisiológica se estiver aderida.',
                    'Observar aspecto, dimensão, profundidade, bordas, leito, odor e características do exsudato.',
                    'Desprezar as luvas, higienizar as mãos novamente e calçar luvas estéreis.',
                    'Irrigar a ferida com solução fisiológica a 0,9% sob leve pressão, do local menos contaminado para o mais contaminado.',
                    'Secar a pele perilesional com gaze estéril, sem friccionar o leito da ferida.',
                    'Aplicar a cobertura prescrita, cobrindo integralmente a lesão e transbordando cerca de 2 cm nas bordas.',
                    'Fixar a cobertura, identificar com data, hora e responsável, e desprezar os resíduos como material infectante.',
                ],
                'cuidados' => [
                    'Iniciar sempre pelas feridas limpas quando o mesmo paciente possui múltiplas lesões, deixando as contaminadas por último.',
                    'Não utilizar antissépticos citotóxicos no leito da ferida em granulação sem indicação específica.',
                    'Reavaliar a periodicidade da troca conforme saturação da cobertura, e não apenas por rotina.',
                    'Comunicar sinais de infecção: hiperemia perilesional progressiva, calor, exsudato purulento, odor fétido e dor crescente.',
                ],
                'registro' => 'Registrar a avaliação da lesão (localização, dimensões, tecidos presentes, exsudato), a cobertura utilizada, a tolerância do paciente e a data da próxima troca.',
            ],
            [
                'title' => 'Prevenção e Tratamento de Lesão por Pressão',
                'slug' => 'prevencao-e-tratamento-de-lesao-por-pressao',
                'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
                'short_description' => 'Classificação por estágios, medidas preventivas com escala de Braden e condutas de curativo para lesões por pressão.',
                'objetivo' => 'Prevenir a lesão de pele e tecidos moles decorrente de pressão prolongada sobre proeminências ósseas e, quando já instalada, tratar a lesão conforme o estágio e as características do leito.',
                'materiais' => [
                    'Escala de Braden ou instrumento institucional de avaliação de risco',
                    'Superfícies de redistribuição de pressão, coxins e travesseiros',
                    'Solução fisiológica a 0,9%, gaze estéril e coberturas específicas (espuma, hidrocoloide, alginato, hidrogel, carvão ativado)',
                    'Ácidos graxos essenciais ou creme barreira para a pele íntegra',
                    'Material de curativo estéril e luvas',
                ],
                'tecnica' => [
                    'Aplicar a escala de Braden na admissão e reavaliar conforme a rotina institucional e a mudança de quadro clínico.',
                    'Inspecionar diariamente a pele, com atenção a sacro, calcâneos, trocânteres, maléolos, occipital e regiões sob dispositivos.',
                    'Reposicionar o paciente a cada 2 horas no leito e a cada 15 a 30 minutos na poltrona, evitando apoio direto sobre a lesão.',
                    'Manter a cabeceira elevada no máximo a 30 graus, quando a condição clínica permitir, para reduzir cisalhamento.',
                    'Classificar a lesão existente: estágio 1 (eritema não branqueável), 2 (perda parcial da derme), 3 (perda total da pele), 4 (exposição de músculo, tendão ou osso), além de lesão não classificável e tissular profunda.',
                    'Limpar o leito com solução fisiológica a 0,9%, sem esfregar o tecido de granulação.',
                    'Selecionar a cobertura pela quantidade de exsudato e pelo tipo de tecido: alginato ou espuma para exsudato abundante, hidrogel para tecido desvitalizado seco, carvão ativado na presença de odor e infecção.',
                    'Proteger a pele perilesional e manter a lesão coberta até a próxima avaliação programada.',
                ],
                'cuidados' => [
                    'Não massagear proeminências ósseas nem utilizar luvas de água ou almofadas circulares — ambas aumentam o risco de lesão.',
                    'Manter a pele limpa, seca e hidratada; controlar a umidade por incontinência com produtos barreira.',
                    'Avaliar o estado nutricional e comunicar a necessidade de suporte, pois o déficit proteico retarda a cicatrização.',
                    'Lesão em estágio 1 não deve receber cobertura oclusiva sem alívio efetivo da pressão.',
                ],
                'registro' => 'Registrar escore de Braden, estágio e mensuração da lesão, cobertura utilizada, horários de reposicionamento e evolução do leito da ferida.',
            ],
            [
                'title' => 'Curativo de Ferida Operatória e Retirada de Pontos',
                'slug' => 'curativo-de-ferida-operatoria-e-retirada-de-pontos',
                'category' => Procedure::CATEGORY_CURATIVOS_FERIDAS,
                'short_description' => 'Cuidados com a incisão cirúrgica no pós-operatório, identificação de deiscência e infecção e técnica de retirada de suturas.',
                'objetivo' => 'Manter a incisão cirúrgica protegida nas primeiras 24 a 48 horas, acompanhar a cicatrização por primeira intenção e remover os pontos no prazo indicado, sem traumatizar a linha de sutura.',
                'materiais' => [
                    'Pacote de curativo estéril e lâmina de bisturi ou tesoura de Iris estéril para a retirada de pontos',
                    'Solução fisiológica a 0,9%, clorexidina alcoólica a 0,5% para a pele íntegra',
                    'Gaze estéril, cobertura oclusiva e fita adesiva hipoalergênica',
                    'Luvas estéreis e de procedimento, óculos e máscara',
                ],
                'tecnica' => [
                    'Higienizar as mãos, explicar o procedimento e posicionar o paciente expondo apenas a área necessária.',
                    'Remover a cobertura anterior com luvas de procedimento e avaliar a incisão: aproximação das bordas, secreção, hiperemia, calor e deiscência.',
                    'Trocar as luvas por estéreis e limpar a incisão com solução fisiológica em movimento único, no sentido da linha de sutura para a periferia.',
                    'Manter cobertura estéril nas primeiras 24 a 48 horas; após esse período, a incisão limpa e seca pode permanecer exposta, conforme protocolo institucional.',
                    'Para a retirada de pontos, verificar a prescrição e o tempo de cicatrização (habitualmente 7 a 10 dias, variando por região).',
                    'Elevar o nó com a pinça, cortar o fio rente à pele do lado oposto ao nó e tracionar para o lado da secção, de modo que nenhum segmento externo atravesse o trajeto.',
                    'Retirar pontos alternados quando houver dúvida sobre a resistência da cicatriz e reavaliar antes de remover os demais.',
                    'Limpar novamente a área e aplicar cobertura, se indicada.',
                ],
                'cuidados' => [
                    'Suspender a retirada de pontos e comunicar o cirurgião diante de deiscência, secreção purulenta ou bordas não coaptadas.',
                    'Orientar o paciente a manter a incisão seca, não aplicar produtos caseiros e proteger a cicatriz do sol por vários meses.',
                    'Avaliar drenos e dispositivos adjacentes no mesmo momento do curativo, registrando aspecto e volume drenado.',
                    'Em feridas com múltiplas incisões, iniciar pela mais limpa.',
                ],
                'registro' => 'Registrar o aspecto da incisão, o número de pontos retirados, as intercorrências, as orientações fornecidas e o encaminhamento quando houver sinais de infecção.',
            ],

            // ==========================================
            // 3. ELIMINAÇÕES (SONDAS E ENEMAS)
            // ==========================================
            [
                'title' => 'Sondagem Vesical de Alívio',
                'slug' => 'sondagem-vesical-de-alivio',
                'category' => Procedure::CATEGORY_ELIMINACOES,
                'short_description' => 'Cateterismo vesical intermitente para esvaziamento da bexiga, coleta de urina estéril e avaliação de resíduo pós-miccional.',
                'objetivo' => 'Esvaziar a bexiga de forma pontual, aliviando a retenção urinária aguda, coletando amostra estéril ou mensurando resíduo, retirando o cateter imediatamente após o procedimento.',
                'materiais' => [
                    'Bandeja de cateterismo estéril: cuba rim, cuba redonda, campo fenestrado, gaze e pinça',
                    'Cateter uretral de calibre adequado (habitualmente 8 a 14 Fr, conforme idade e sexo)',
                    'Gel lubrificante estéril, preferencialmente com anestésico',
                    'Solução antisséptica aquosa (clorexidina aquosa a 0,2%) e solução fisiológica',
                    'Luvas estéreis, luvas de procedimento, foco de luz e frasco coletor',
                ],
                'tecnica' => [
                    'Explicar o procedimento, garantir privacidade e higienizar as mãos.',
                    'Realizar a higiene íntima prévia com água e sabão.',
                    'Posicionar: mulheres em decúbito dorsal com joelhos fletidos e afastados; homens em decúbito dorsal com membros estendidos.',
                    'Abrir o material estéril mantendo a técnica asséptica e calçar luvas estéreis.',
                    'Realizar a antissepsia: na mulher, afastar os grandes lábios e limpar de cima para baixo, do meato para a periferia; no homem, tracionar o prepúcio e limpar da glande para a base.',
                    'Colocar o campo fenestrado e lubrificar generosamente o cateter.',
                    'Introduzir o cateter até o retorno de urina — cerca de 4 a 6 cm na mulher e 18 a 20 cm no homem, mantendo o pênis a 90 graus durante a progressão.',
                    'Drenar a urina no frasco coletor e retirar o cateter lentamente ao término do fluxo.',
                    'Recompor o prepúcio, secar a região, desprezar o material e higienizar as mãos.',
                ],
                'cuidados' => [
                    'Nunca forçar a progressão diante de resistência: interromper e comunicar, pelo risco de falso trajeto e trauma uretral.',
                    'Em bexigas muito distendidas, drenar de forma gradual conforme protocolo institucional, monitorando queixas e sinais vitais.',
                    'Manter a técnica estéril durante todo o procedimento — a quebra da assepsia é a principal causa de infecção do trato urinário associada ao cateter.',
                    'Contraindicado em suspeita de trauma uretral (uretrorragia, hematoma perineal) até avaliação especializada.',
                ],
                'registro' => 'Registrar indicação, calibre do cateter, volume drenado, aspecto da urina, intercorrências e tolerância do paciente.',
            ],
            [
                'title' => 'Sondagem Vesical de Demora',
                'slug' => 'sondagem-vesical-de-demora',
                'category' => Procedure::CATEGORY_ELIMINACOES,
                'short_description' => 'Instalação e manutenção do cateter vesical de demora em sistema fechado, com foco na prevenção de ITU associada a cateter.',
                'objetivo' => 'Manter drenagem urinária contínua em situações de retenção persistente, controle rigoroso de diurese em pacientes graves ou necessidade cirúrgica, sempre pelo menor tempo possível.',
                'materiais' => [
                    'Cateter de Foley de duas ou três vias, calibre conforme indicação',
                    'Bolsa coletora de sistema fechado com válvula antirrefluxo',
                    'Seringa de 10 ou 20 mL com água destilada estéril para insuflar o balonete',
                    'Bandeja de cateterismo estéril, gel lubrificante estéril e antisséptico aquoso',
                    'Luvas estéreis, campo fenestrado e dispositivo de fixação',
                ],
                'tecnica' => [
                    'Confirmar a indicação clínica: a sondagem por conveniência ou apenas para controle de incontinência deve ser evitada.',
                    'Higienizar as mãos, realizar higiene íntima e preparar o material mantendo a assepsia.',
                    'Testar o balonete antes da introdução, conforme orientação do fabricante, e conectar previamente a bolsa coletora quando o sistema for pré-conectado.',
                    'Realizar antissepsia e colocar o campo fenestrado com luvas estéreis.',
                    'Lubrificar o cateter e introduzi-lo até o retorno de urina, progredindo mais 2 a 4 cm para garantir que o balonete esteja na bexiga.',
                    'Insuflar o balonete com o volume indicado no cateter, utilizando água destilada estéril — nunca solução fisiológica ou ar.',
                    'Tracionar delicadamente até sentir a resistência do colo vesical e conectar ao sistema fechado.',
                    'Fixar o cateter na coxa (mulher) ou no abdome (homem), sem tração, e posicionar a bolsa abaixo do nível da bexiga, sem encostar no chão.',
                ],
                'cuidados' => [
                    'Não desconectar o sistema fechado para coleta: utilizar o dispositivo específico do cateter, com antissepsia prévia.',
                    'Manter o circuito livre de dobras e a bolsa sempre abaixo da bexiga para evitar refluxo.',
                    'Realizar higiene íntima diária com água e sabão; irrigação vesical de rotina não é recomendada.',
                    'Reavaliar diariamente a necessidade do cateter e retirá-lo assim que possível — o tempo de permanência é o principal fator de risco para ITU.',
                ],
                'registro' => 'Registrar data e hora da instalação, calibre, volume do balonete, aspecto e volume da diurese, além da reavaliação diária da indicação.',
            ],
            [
                'title' => 'Enema e Lavagem Intestinal',
                'slug' => 'enema-e-lavagem-intestinal',
                'category' => Procedure::CATEGORY_ELIMINACOES,
                'short_description' => 'Administração de solução por via retal para tratamento de constipação, preparo de exames e procedimentos cirúrgicos.',
                'objetivo' => 'Introduzir solução no reto e no cólon distal para estimular a peristalse, amolecer as fezes e promover a eliminação intestinal, seja com finalidade terapêutica, evacuativa ou de preparo.',
                'materiais' => [
                    'Solução prescrita (fosfato de sódio pronto para uso, glicerinado ou solução fisiológica morna)',
                    'Sonda retal de calibre adequado ou frasco com cânula própria',
                    'Irrigador ou seringa de bico, gel lubrificante hidrossolúvel',
                    'Comadre, papel higiênico, forro impermeável e material de higiene',
                    'Luvas de procedimento e avental',
                ],
                'tecnica' => [
                    'Conferir a prescrição, explicar o procedimento e garantir privacidade.',
                    'Higienizar as mãos, calçar luvas e proteger o leito com forro impermeável.',
                    'Posicionar o paciente em decúbito lateral esquerdo (posição de Sims), com a perna direita fletida.',
                    'Aquecer a solução à temperatura corporal, evitando extremos que causem cólica ou lesão da mucosa.',
                    'Lubrificar a extremidade da sonda ou cânula e retirar o ar do sistema.',
                    'Introduzir a sonda cerca de 7 a 10 cm no adulto, delicadamente, direcionando-a para o umbigo.',
                    'Infundir a solução lentamente, em 5 a 10 minutos, interrompendo se houver cólica intensa.',
                    'Retirar a sonda e orientar o paciente a reter a solução pelo tempo indicado (habitualmente 5 a 15 minutos).',
                    'Auxiliar na eliminação, observar as características do retorno e realizar a higiene íntima.',
                ],
                'cuidados' => [
                    'Contraindicado em suspeita de abdome agudo, obstrução intestinal, sangramento retal ativo, pós-operatório recente de cirurgia colorretal e neutropenia grave, salvo indicação médica expressa.',
                    'Interromper imediatamente diante de dor intensa, sangramento, sudorese, bradicardia ou hipotensão.',
                    'Nunca forçar a introdução da sonda diante de resistência ou massa fecal endurecida.',
                    'Monitorar hidratação e eletrólitos em pacientes idosos ou com uso repetido de soluções fosfatadas.',
                ],
                'registro' => 'Registrar solução e volume administrados, tempo de retenção, características e volume do retorno, além da resposta do paciente.',
            ],

            // ==========================================
            // 4. CUIDADOS COM VIAS AÉREAS
            // ==========================================
            [
                'title' => 'Aspiração de Vias Aéreas Superiores',
                'slug' => 'aspiracao-de-vias-aereas-superiores',
                'category' => Procedure::CATEGORY_VIAS_AEREAS,
                'short_description' => 'Remoção de secreções da orofaringe e nasofaringe com técnica asséptica, tempo controlado e monitorização do paciente.',
                'objetivo' => 'Manter as vias aéreas pérvias em pacientes incapazes de eliminar secreções de forma eficaz, melhorando a ventilação e prevenindo broncoaspiração e atelectasia.',
                'materiais' => [
                    'Aspirador a vácuo (parede ou portátil) com frasco coletor e extensão',
                    'Sonda de aspiração estéril de calibre compatível',
                    'Luvas estéreis e de procedimento, máscara e óculos de proteção',
                    'Solução fisiológica a 0,9% estéril para lavagem da extensão',
                    'Fonte de oxigênio e oxímetro de pulso disponíveis',
                ],
                'tecnica' => [
                    'Avaliar a indicação: ruídos adventícios, secreção visível, queda de saturação, desconforto respiratório ou tosse ineficaz.',
                    'Explicar o procedimento, elevar a cabeceira e monitorar oximetria.',
                    'Higienizar as mãos, colocar máscara e óculos e testar a pressão de vácuo (habitualmente 80 a 120 mmHg no adulto).',
                    'Abrir a sonda mantendo a esterilidade e calçar as luvas, considerando estéril a mão dominante.',
                    'Introduzir a sonda sem aspirar: pela boca até a orofaringe ou pela narina em trajeto paralelo ao palato.',
                    'Aplicar a aspiração de forma intermitente durante a retirada, com movimentos rotatórios, por no máximo 10 a 15 segundos por vez.',
                    'Permitir a recuperação do paciente entre as aspirações, oferecendo oxigênio quando indicado.',
                    'Lavar a extensão com solução fisiológica, desprezar a sonda e o material, e higienizar as mãos.',
                ],
                'cuidados' => [
                    'Aspirar sempre da região mais limpa para a mais contaminada: cânula ou traqueia primeiro, depois nariz e por último a boca, trocando a sonda.',
                    'Interromper diante de bradicardia, arritmia, queda importante de saturação, sangramento ou agitação intensa.',
                    'Não instilar solução fisiológica na via aérea de rotina — a prática não tem benefício comprovado e aumenta o risco de infecção.',
                    'Manter a aspiração como procedimento sob demanda, guiado por avaliação clínica, e não em horários fixos.',
                ],
                'registro' => 'Registrar a indicação, o número de aspirações, o aspecto, o volume e o odor da secreção, os parâmetros de oximetria e as intercorrências.',
            ],
            [
                'title' => 'Oxigenoterapia por Cateter Nasal e Máscara',
                'slug' => 'oxigenoterapia-por-cateter-nasal-e-mascara',
                'category' => Procedure::CATEGORY_VIAS_AEREAS,
                'short_description' => 'Escolha do dispositivo, fluxos correspondentes e cuidados de enfermagem na administração de oxigênio suplementar.',
                'objetivo' => 'Ofertar oxigênio suplementar para corrigir ou prevenir a hipoxemia, mantendo a saturação dentro da meta definida para o paciente com o menor fluxo eficaz.',
                'materiais' => [
                    'Fonte de oxigênio com fluxômetro e umidificador, quando indicado',
                    'Cateter nasal tipo óculos, máscara simples, máscara de Venturi ou máscara com reservatório',
                    'Oxímetro de pulso e material de fixação',
                    'Solução fisiológica ou hidratante nasal para conforto da mucosa',
                ],
                'tecnica' => [
                    'Conferir a prescrição de fluxo ou de meta de saturação e avaliar o padrão respiratório do paciente.',
                    'Higienizar as mãos, explicar o procedimento e posicionar o paciente com a cabeceira elevada.',
                    'Selecionar o dispositivo: cateter nasal (1 a 6 L/min), máscara simples (5 a 10 L/min), máscara de Venturi (fração inspirada controlada) ou máscara com reservatório (10 a 15 L/min).',
                    'Instalar o dispositivo e ajustar o fluxo prescrito, confirmando o borbulhamento do umidificador quando utilizado.',
                    'Verificar o ajuste ao rosto do paciente, evitando pontos de pressão em orelhas, nariz e face.',
                    'Monitorar a saturação e o padrão respiratório após a instalação e a cada mudança de fluxo.',
                    'Reavaliar periodicamente a possibilidade de redução ou retirada do suporte.',
                ],
                'cuidados' => [
                    'Em pacientes com retenção crônica de gás carbônico, respeitar metas de saturação mais baixas conforme prescrição, pelo risco de hipercapnia.',
                    'Manter a máscara com reservatório sempre insuflada; um reservatório colapsado indica fluxo insuficiente.',
                    'Inspecionar a pele sob o dispositivo a cada turno para prevenir lesão por pressão relacionada a dispositivo.',
                    'Oxigênio é medicamento: alterações de fluxo exigem prescrição, exceto em situações de emergência devidamente registradas.',
                ],
                'registro' => 'Registrar o dispositivo, o fluxo, a saturação antes e depois, o padrão respiratório e a tolerância do paciente.',
            ],
            [
                'title' => 'Cuidados com Traqueostomia',
                'slug' => 'cuidados-com-traqueostomia',
                'category' => Procedure::CATEGORY_VIAS_AEREAS,
                'short_description' => 'Higiene do estoma, troca de cadarço, limpeza da cânula interna e conduta diante de decanulação acidental.',
                'objetivo' => 'Manter a cânula pérvia e o estoma íntegro, prevenindo infecção, obstrução por secreção e lesão de pele, além de preparar a equipe para a emergência de decanulação.',
                'materiais' => [
                    'Material de curativo estéril, gaze sem algodão (do tipo não desfiante) e solução fisiológica a 0,9%',
                    'Cadarço ou fixador próprio para traqueostomia',
                    'Escova ou material para limpeza da cânula interna, quando o modelo permitir',
                    'Sistema de aspiração montado e testado',
                    'Cânula reserva do mesmo número e uma de número imediatamente inferior, mantidas à beira do leito',
                ],
                'tecnica' => [
                    'Higienizar as mãos, explicar o procedimento e posicionar o paciente com a cabeceira elevada.',
                    'Aspirar a cânula se houver secreção, antes de iniciar a limpeza do estoma.',
                    'Remover o curativo anterior e avaliar o estoma: hiperemia, secreção, granulação, enfisema subcutâneo e sangramento.',
                    'Limpar a pele ao redor com solução fisiológica, do estoma para a periferia, e secar bem.',
                    'Retirar a cânula interna (quando o modelo possuir), limpá-la conforme orientação do fabricante e recolocá-la travada corretamente.',
                    'Aplicar gaze não desfiante sob o flange da cânula, sem cortar gaze comum, evitando fiapos na via aérea.',
                    'Trocar o cadarço com auxílio de um segundo profissional, mantendo a cânula firmemente segura durante toda a manobra.',
                    'Ajustar a fixação deixando espaço para um dedo entre o cadarço e o pescoço.',
                ],
                'cuidados' => [
                    'Nunca realizar a troca de cadarço sozinho em traqueostomia recente: o risco de decanulação acidental é alto e o trajeto ainda não está maduro.',
                    'Diante de decanulação acidental, manter o estoma aberto, oferecer oxigênio, acionar imediatamente a equipe e não tentar reintroduzir a cânula às cegas em traqueostomia com menos de sete dias.',
                    'Manter umidificação adequada da via aérea para evitar rolha de secreção.',
                    'Estabelecer comunicação alternativa com o paciente e registrar suas necessidades, já que a fala pode estar ausente.',
                ],
                'registro' => 'Registrar o aspecto do estoma, as características da secreção, a troca de cadarço e de curativo, o número da cânula e qualquer intercorrência respiratória.',
            ],

            // ==========================================
            // 5. SONDAS ALIMENTARES
            // ==========================================
            [
                'title' => 'Sondagem Nasogástrica',
                'slug' => 'sondagem-nasogastrica',
                'category' => Procedure::CATEGORY_SONDAS_ALIMENTARES,
                'short_description' => 'Passagem de sonda até o estômago para drenagem, descompressão gástrica ou administração de dieta e medicamentos.',
                'objetivo' => 'Estabelecer via de acesso ao estômago para drenagem de conteúdo gástrico, descompressão em quadros obstrutivos, lavagem gástrica ou administração de dieta e medicamentos por curto prazo.',
                'materiais' => [
                    'Sonda nasogástrica de calibre adequado (habitualmente 14 a 18 Fr no adulto)',
                    'Gel lubrificante hidrossolúvel, seringa de 20 mL e estetoscópio',
                    'Fita adesiva hipoalergênica ou fixador nasal, copo com água e canudo',
                    'Luvas de procedimento, toalha e cuba rim',
                    'Bolsa coletora, quando o objetivo for drenagem',
                ],
                'tecnica' => [
                    'Explicar o procedimento, combinar sinais com o paciente e posicioná-lo sentado ou com a cabeceira a 45 graus.',
                    'Higienizar as mãos, calçar luvas e avaliar a permeabilidade das narinas.',
                    'Medir a sonda da ponta do nariz ao lóbulo da orelha e deste ao apêndice xifoide, marcando a distância.',
                    'Lubrificar a extremidade distal da sonda com gel hidrossolúvel.',
                    'Introduzir a sonda pela narina, direcionando-a para trás e para baixo, respeitando o assoalho nasal.',
                    'Ao atingir a orofaringe, solicitar que o paciente flexione a cabeça e degluta, progredindo a sonda a cada deglutição.',
                    'Interromper imediatamente diante de tosse intensa, cianose, dispneia ou saída da sonda pela boca, retirando-a e reiniciando após recuperação.',
                    'Confirmar o posicionamento conforme protocolo institucional — a radiografia é o padrão-ouro; aspiração de conteúdo gástrico e teste de pH complementam a avaliação.',
                    'Fixar a sonda sem tracionar a asa do nariz e conectar à bolsa coletora ou fechá-la, conforme a indicação.',
                ],
                'cuidados' => [
                    'A ausculta epigástrica isolada não é método confiável de confirmação e não deve ser usada como único critério para liberar o uso da sonda.',
                    'Contraindicada a passagem nasal em suspeita de fratura de base de crânio ou trauma maxilofacial grave — nesses casos, avaliar a via orogástrica.',
                    'Inspecionar diariamente a narina e alternar o ponto de fixação para prevenir lesão por pressão.',
                    'Manter a marcação externa da sonda registrada e conferi-la a cada turno para detectar deslocamento.',
                ],
                'registro' => 'Registrar calibre, narina utilizada, medida externa, método de confirmação do posicionamento, aspecto e volume drenado e tolerância do paciente.',
            ],
            [
                'title' => 'Sondagem Nasoenteral e Administração de Dieta',
                'slug' => 'sondagem-nasoenteral-e-administracao-de-dieta',
                'category' => Procedure::CATEGORY_SONDAS_ALIMENTARES,
                'short_description' => 'Instalação da sonda pós-pilórica, confirmação radiológica e administração segura de nutrição enteral.',
                'objetivo' => 'Fornecer nutrição enteral em posição pós-pilórica, reduzindo o risco de broncoaspiração em pacientes com esvaziamento gástrico retardado, refluxo importante ou necessidade de suporte nutricional prolongado.',
                'materiais' => [
                    'Sonda enteral de poliuretano ou silicone com fio guia, calibre 8 a 12 Fr',
                    'Gel lubrificante hidrossolúvel, seringa de 20 mL e água filtrada ou estéril',
                    'Fixador nasal, luvas de procedimento e estetoscópio',
                    'Dieta enteral prescrita, equipo específico e bomba de infusão, quando indicada',
                ],
                'tecnica' => [
                    'Conferir a prescrição e checar a via de administração antes de qualquer manipulação.',
                    'Posicionar o paciente sentado ou com a cabeceira elevada a no mínimo 45 graus.',
                    'Medir e introduzir a sonda como na sondagem nasogástrica, acrescentando cerca de 10 a 15 cm para alcançar a posição pós-pilórica.',
                    'Posicionar o paciente em decúbito lateral direito para favorecer a migração transpilórica, quando não houver contraindicação.',
                    'Solicitar a confirmação radiológica: a dieta só pode ser iniciada após a liberação do exame.',
                    'Retirar o fio guia somente após a confirmação e jamais reintroduzi-lo com a sonda posicionada, pelo risco de perfuração.',
                    'Administrar a dieta na velocidade prescrita, em sistema fechado sempre que disponível, respeitando o tempo máximo de infusão do produto.',
                    'Lavar a sonda com 20 a 40 mL de água antes e depois da dieta e de cada medicamento.',
                ],
                'cuidados' => [
                    'Manter a cabeceira elevada entre 30 e 45 graus durante a infusão e por pelo menos 30 minutos após o término.',
                    'Não misturar medicamentos com a dieta nem administrar comprimidos triturados de liberação prolongada pela sonda.',
                    'Monitorar distensão abdominal, náusea, vômito, diarreia e resíduo gástrico conforme protocolo, comunicando alterações.',
                    'Conferir a marcação externa a cada turno e suspender a dieta imediatamente diante de suspeita de deslocamento ou tosse durante a infusão.',
                ],
                'registro' => 'Registrar a confirmação radiológica, a marcação externa, o volume e o tipo de dieta infundida, a lavagem da sonda e a tolerância gastrointestinal.',
            ],
            [
                'title' => 'Cuidados com Gastrostomia',
                'slug' => 'cuidados-com-gastrostomia',
                'category' => Procedure::CATEGORY_SONDAS_ALIMENTARES,
                'short_description' => 'Higiene do estoma, prevenção de lesão periestomal, administração de dieta e conduta na saída acidental da sonda.',
                'objetivo' => 'Manter a via de alimentação por gastrostomia funcionante e o estoma íntegro em pacientes com necessidade de suporte nutricional enteral prolongado.',
                'materiais' => [
                    'Água morna, sabão neutro, gaze e solução fisiológica a 0,9%',
                    'Seringa de bico ou de ponta cateter de 20 a 60 mL',
                    'Dieta prescrita, equipo e água filtrada para lavagem',
                    'Luvas de procedimento e cobertura, quando indicada',
                ],
                'tecnica' => [
                    'Higienizar as mãos, explicar o procedimento e posicionar o paciente com a cabeceira elevada a no mínimo 45 graus.',
                    'Limpar o estoma diariamente com água morna e sabão neutro, em movimentos circulares do centro para a periferia, e secar bem.',
                    'Avaliar a pele periestomal: hiperemia, secreção, tecido de granulação, extravasamento de conteúdo gástrico e dor.',
                    'Girar delicadamente a sonda conforme orientação do serviço, quando o modelo permitir, para prevenir a síndrome do enterramento do disco interno.',
                    'Confirmar o posicionamento e a permeabilidade antes de qualquer administração, lavando com água.',
                    'Administrar a dieta lentamente, por gravidade ou bomba, conforme prescrição.',
                    'Lavar a sonda com 20 a 40 mL de água após a dieta e após cada medicamento, mantendo-a fechada.',
                    'Manter a cabeceira elevada por pelo menos 30 minutos após o término da infusão.',
                ],
                'cuidados' => [
                    'Diante de saída acidental da sonda, cobrir o estoma com gaze estéril e acionar a equipe imediatamente — o trajeto pode fechar em poucas horas.',
                    'Não utilizar produtos irritantes ou antissépticos coloridos que mascarem a avaliação do estoma.',
                    'Comunicar extravasamento persistente de conteúdo gástrico, que causa dermatite química e exige revisão do dispositivo.',
                    'Orientar o cuidador domiciliar sobre higiene, administração da dieta, sinais de alerta e desobstrução com água morna.',
                ],
                'registro' => 'Registrar o aspecto do estoma e da pele periestomal, o volume e o tipo de dieta, a lavagem da sonda, as orientações ao cuidador e as intercorrências.',
            ],
        ];
    }
}
